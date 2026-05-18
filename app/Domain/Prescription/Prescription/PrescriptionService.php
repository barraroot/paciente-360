<?php

namespace App\Domain\Prescription\Prescription;

use App\Domain\Prescription\PrescriptionItem\PrescriptionItem;
use App\Events\Prescription\PrescricaoAtualizada;
use App\Events\Prescription\PrescricaoCancelada;
use App\Events\Prescription\PrescricaoCriada;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * T050 — Serviço de domínio para ciclo de vida de receitas.
 *
 * Responsável por:
 *  - Criação em transação (items + evento + audit).
 *  - Atualização imutável (apenas notes).
 *  - Cancelamento irreversível + evento.
 */
class PrescriptionService
{
    /**
     * Cria uma receita com seus items em transação atômica.
     *
     * Regras enforçadas (defesa em profundidade além do FormRequest):
     *  - `professional_id` forçado para o usuário autenticado.
     *  - `tenant_id` forçado para o tenant do usuário.
     *  - `expires_at` calculado server-side com base em `issued_at` + `duration_days`.
     *  - `status` sempre `active` na criação.
     *  - `source` sempre `manual` na criação via API.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data, User $author): Prescription
    {
        return DB::transaction(function () use ($data, $author): Prescription {
            $type = PrescriptionType::from($data['type']);
            $issuedAt = Carbon::parse($data['issued_at']);
            $expiresAt = $this->calculateExpiresAt($type, $issuedAt, (int) ($data['duration_days'] ?? 30));

            $prescription = Prescription::create([
                'tenant_id' => $author->tenant_id,
                'patient_id' => $data['patient_id'],
                'professional_id' => $author->id,
                'appointment_id' => $data['appointment_id'] ?? null,
                'type' => $type,
                'status' => PrescriptionStatus::Active,
                'source' => PrescriptionSource::Manual,
                'issued_at' => $issuedAt,
                'expires_at' => $expiresAt,
                'alert_disabled' => $type === PrescriptionType::Common
                    ? (bool) ($data['alert_disabled'] ?? false)
                    : false,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $index => $itemData) {
                PrescriptionItem::create([
                    'prescription_id' => $prescription->id,
                    'medication_name' => $itemData['medication_name'],
                    'posology' => $itemData['posology'],
                    'concentration' => $itemData['concentration'] ?? null,
                    'pharmaceutical_form' => $itemData['pharmaceutical_form'] ?? null,
                    'quantity' => $itemData['quantity'] ?? null,
                    'treatment_duration' => $itemData['treatment_duration'] ?? null,
                    'sort_order' => $index,
                ]);
            }

            $this->recordAuditLog(
                action: 'prescription.created',
                prescription: $prescription,
                actorId: $author->id,
                payload: [
                    'type' => $type->value,
                    'patient_id' => $prescription->patient_id,
                    'issued_at' => $issuedAt->toDateString(),
                    'expires_at' => $expiresAt->toDateString(),
                    'items_count' => count($data['items']),
                ],
            );

            PrescricaoCriada::dispatch(
                prescriptionId: $prescription->id,
                tenantId: $prescription->tenant_id,
                patientId: $prescription->patient_id,
                professionalId: $prescription->professional_id,
                type: $type->value,
                issuedAt: $issuedAt,
                expiresAt: $expiresAt,
            );

            return $prescription;
        });
    }

    /**
     * Atualiza apenas as notas da receita (AC-8.1.7).
     *
     * Campos regulatórios (tipo, validade, medicamentos) são imutáveis.
     */
    public function updateNotes(Prescription $prescription, string $notes, User $actor): void
    {
        DB::transaction(function () use ($prescription, $notes, $actor): void {
            $prescription->update(['notes' => $notes]);

            $this->recordAuditLog(
                action: 'prescription.updated',
                prescription: $prescription,
                actorId: $actor->id,
                payload: ['changed_fields' => ['notes']],
            );

            PrescricaoAtualizada::dispatch(
                prescriptionId: $prescription->id,
                changedFields: ['notes'],
            );
        });
    }

    /**
     * Cancela uma receita irreversivelmente (AC-8.1.8).
     *
     * Idempotente: se já cancelada, retorna sem modificar.
     */
    public function cancel(
        Prescription $prescription,
        CancellationReasonCategory $category,
        string $reason,
        User $by,
    ): void {
        // Idempotência: já cancelada → no-op
        if ($prescription->isCancelled()) {
            return;
        }

        DB::transaction(function () use ($prescription, $category, $reason, $by): void {
            $cancelledAt = Carbon::now();

            $prescription->update([
                'status' => PrescriptionStatus::Cancelled,
                'cancelled_at' => $cancelledAt,
                'cancelled_by_user_id' => $by->id,
                'cancellation_reason_category' => $category,
                'cancellation_reason' => $reason,
            ]);

            $this->recordAuditLog(
                action: 'prescription.cancelled',
                prescription: $prescription,
                actorId: $by->id,
                payload: [
                    'cancellation_reason_category' => $category->value,
                    'cancellation_reason' => $reason,
                    'cancelled_at' => $cancelledAt->toIso8601String(),
                ],
            );

            PrescricaoCancelada::dispatch(
                prescriptionId: $prescription->id,
                tenantId: $prescription->tenant_id,
                patientId: $prescription->patient_id,
                cancelledByUserId: $by->id,
                categoryReason: $category->value,
                cancelledAt: $cancelledAt,
            );
        });
    }

    /**
     * Calcula expires_at server-side a partir do tipo e issued_at.
     *
     * - common: issued_at + duration_days (deve estar em {30,60,90,180}).
     * - special/controlled: issued_at + 30 (fixo, Portaria 344/98).
     */
    private function calculateExpiresAt(PrescriptionType $type, Carbon $issuedAt, int $durationDays): Carbon
    {
        $days = match ($type) {
            PrescriptionType::Special, PrescriptionType::Controlled => 30,
            PrescriptionType::Common => $durationDays,
        };

        return $issuedAt->copy()->addDays($days);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function recordAuditLog(
        string $action,
        Prescription $prescription,
        int $actorId,
        array $payload = [],
    ): void {
        try {
            AuditLog::create([
                'tenant_id' => $prescription->tenant_id,
                'user_id' => $actorId,
                'actor_type' => 'user',
                'action' => $action,
                'auditable_type' => Prescription::class,
                'auditable_id' => $prescription->id,
                'payload' => $payload,
                'ip' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        } catch (\Throwable) {
            // Audit failures never block domain operations
        }
    }
}
