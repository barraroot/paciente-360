<?php

namespace App\Http\Resources\Prescriptions;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionType;
use App\Domain\Prescription\Report\ControlledPrescriptionMaskingService;
use App\Events\Prescription\PrescricaoControladaVisualizada;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * T066 — Resource de receita com mascaramento de controladas (Q8).
 *
 * Quando o user autenticado não pode `viewControlled` em receita controlada,
 * usa `ControlledPrescriptionMaskingService` para retornar shape mascarado.
 *
 * Emite `PrescricaoControladaVisualizada` UMA VEZ por serialização quando
 * o usuário tem acesso completo a uma receita controlada.
 *
 * @mixin Prescription
 */
class PrescriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Prescription $prescription */
        $prescription = $this->resource;
        /** @var User|null $user */
        $user = $request->user();

        if ($prescription->type === PrescriptionType::Controlled && $user !== null) {
            return $this->toArrayWithMasking($prescription, $user, $request);
        }

        return $this->toArrayFull($prescription);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArrayWithMasking(Prescription $prescription, User $user, Request $request): array
    {
        $service = new ControlledPrescriptionMaskingService;
        $data = $service->mask($prescription, $user);

        // Emite audit event quando o usuário vê conteúdo completo
        if (! ($data['masked'] ?? false)) {
            PrescricaoControladaVisualizada::dispatch(
                actorUserId: $user->id,
                prescriptionId: $prescription->id,
                tenantId: $prescription->tenant_id,
                viewedAt: Carbon::now(),
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        }

        $items = $data['masked'] ?? false
            ? []
            : PrescriptionItemResource::collection($prescription->items->all())->resolve($request);

        return array_merge($data, [
            'id' => $prescription->id,
            'tenant_id' => $prescription->tenant_id,
            'appointment_id' => $prescription->appointment_id,
            'alert_disabled' => $prescription->alert_disabled,
            'pdf_path' => $prescription->pdf_path,
            'pdf_version' => $prescription->pdf_version,
            'criticality' => $prescription->criticality(),
            'items' => $items,
            'created_at' => $prescription->created_at?->toIso8601String(),
            'updated_at' => $prescription->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArrayFull(Prescription $prescription): array
    {
        return [
            'id' => $prescription->id,
            'tenant_id' => $prescription->tenant_id,
            'patient_id' => $prescription->patient_id,
            'professional_id' => $prescription->professional_id,
            'appointment_id' => $prescription->appointment_id,
            'type' => $prescription->type?->value,
            'status' => $prescription->status?->value,
            'issued_at' => $prescription->issued_at?->toDateString(),
            'expires_at' => $prescription->expires_at?->toDateString(),
            'alert_disabled' => $prescription->alert_disabled,
            'notes' => $prescription->notes,
            'pdf_path' => $prescription->pdf_path,
            'pdf_version' => $prescription->pdf_version,
            'criticality' => $prescription->criticality(),
            'masked' => false,
            'cancellation_reason_category' => $prescription->cancellation_reason_category?->value,
            'cancellation_reason' => $prescription->cancellation_reason,
            'cancelled_at' => $prescription->cancelled_at?->toIso8601String(),
            'items' => PrescriptionItemResource::collection($prescription->items)->resolve(),
            'created_at' => $prescription->created_at?->toIso8601String(),
            'updated_at' => $prescription->updated_at?->toIso8601String(),
        ];
    }
}
