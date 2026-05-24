<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Services;

use App\Domain\Privacy\Events\ConsentimentoRecusado;
use App\Domain\Privacy\Events\ConsentimentoRegistrado;
use App\Domain\Privacy\Events\ConsentimentoRevogado;
use App\Domain\Privacy\Models\ConsentFinalidade;
use App\Domain\Privacy\Models\ConsentRecord;
use App\Domain\Privacy\Models\ConsentState;
use App\Models\Paciente;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * **T024 (Fase 8 — Lote A US-13.1)** — Serviço centralizado de consentimento LGPD.
 *
 * Única camada autorizada a:
 *   - Criar registros de consentimento granular (Q24).
 *   - Aplicar revogação respeitando regras de granularidade (Q25).
 *   - Verificar `hasGranted()` para gates externos (campanhas Lote C, webhooks Lote D).
 *
 * **Idempotência**: chamadas duplicadas a `record()` para o mesmo (patient, finalidade)
 * são detectadas pela PARTIAL UNIQUE constraint; o serviço retorna o registro
 * existente em vez de criar duplicata.
 *
 * **Atomicidade**: revogação é transação: cria linha state='revoked' E atualiza
 * a linha granted anterior. Ambas devem ter sucesso ou nenhuma.
 */
// NB: não-final para permitir doubling (createMock) em testes de integração
// que dependem de ConsentService injetado (ex: WebhookDispatcher).
class ConsentService
{
    /**
     * Registra um novo consentimento (state=granted).
     *
     * Se já existe consentimento ativo para (paciente, finalidade), retorna esse
     * registro inalterado. Idempotência por PARTIAL UNIQUE.
     */
    public function record(
        Paciente $patient,
        string $channel,
        ConsentFinalidade $finalidade,
        ?int $evidenceMessageId = null,
        ?array $evidenceSnapshot = null,
        string $termsVersion = '1.0',
    ): ConsentRecord {
        $existing = ConsentRecord::query()
            ->where('patient_id', $patient->id)
            ->forFinalidade($finalidade)
            ->granted()
            ->first();

        if ($existing instanceof ConsentRecord) {
            return $existing;
        }

        $now = Carbon::now();

        $record = DB::transaction(function () use ($patient, $channel, $finalidade, $evidenceMessageId, $evidenceSnapshot, $termsVersion, $now): ConsentRecord {
            return ConsentRecord::query()->create([
                'tenant_id' => $patient->tenant_id,
                'patient_id' => $patient->id,
                'channel' => $channel,
                'finalidade' => $finalidade,
                'state' => ConsentState::Granted,
                'granted_at' => $now,
                'revoked_at' => null,
                'evidence_message_id' => $evidenceMessageId,
                'evidence_snapshot' => $evidenceSnapshot,
                'terms_version' => $termsVersion,
                'scope' => null,
            ]);
        });

        Event::dispatch(new ConsentimentoRegistrado(
            tenantId: $patient->tenant_id,
            patientId: $patient->id,
            consentRecordId: $record->id,
            channel: $channel,
            finalidade: $finalidade,
            grantedAt: $now,
            evidenceMessageId: $evidenceMessageId,
            termsVersion: $termsVersion,
        ));

        return $record;
    }

    /**
     * Registra uma RECUSA explícita. Usado quando o paciente responde "não"
     * à pergunta de opt-in (em vez de simplesmente ignorar).
     *
     * Recusa NÃO bloqueia transacional (que é implícito ao cadastro).
     */
    public function refuse(
        Paciente $patient,
        string $channel,
        ConsentFinalidade $finalidade,
        ?int $evidenceMessageId = null,
        ?array $evidenceSnapshot = null,
        string $termsVersion = '1.0',
    ): ConsentRecord {
        $now = Carbon::now();

        $record = DB::transaction(fn () => ConsentRecord::query()->create([
            'tenant_id' => $patient->tenant_id,
            'patient_id' => $patient->id,
            'channel' => $channel,
            'finalidade' => $finalidade,
            'state' => ConsentState::Refused,
            'granted_at' => null,
            'revoked_at' => null,
            'evidence_message_id' => $evidenceMessageId,
            'evidence_snapshot' => $evidenceSnapshot,
            'terms_version' => $termsVersion,
            'scope' => null,
        ]));

        Event::dispatch(new ConsentimentoRecusado(
            tenantId: $patient->tenant_id,
            patientId: $patient->id,
            consentRecordId: $record->id,
            channel: $channel,
            finalidade: $finalidade,
            refusedAt: $now,
            evidenceMessageId: $evidenceMessageId,
            termsVersion: $termsVersion,
        ));

        return $record;
    }

    /**
     * Revoga consentimento ativo. Q25 — granularidade por canal vs. global.
     *
     * Scope:
     *   - 'channel' (default): revoga apenas o canal informado se outros canais
     *     têm consentimento ativo. NOTA: como nosso modelo é por (patient,
     *     finalidade), a revogação efetiva sempre é por finalidade — o `scope`
     *     fica como metadata de auditoria sobre o intent do paciente.
     *   - 'all': revoga em todos os canais (intent explícito do usuário).
     *
     * @return list<ConsentRecord> registros revogados (vazio se não havia ativo).
     */
    public function revoke(
        Paciente $patient,
        ConsentFinalidade $finalidade,
        string $channel,
        ?int $evidenceMessageId = null,
        string $scope = 'all',
    ): array {
        $now = Carbon::now();

        return DB::transaction(function () use ($patient, $finalidade, $channel, $evidenceMessageId, $scope, $now): array {
            $activeRecords = ConsentRecord::query()
                ->where('patient_id', $patient->id)
                ->forFinalidade($finalidade)
                ->granted()
                ->lockForUpdate()
                ->get();

            $revoked = [];

            foreach ($activeRecords as $record) {
                $record->update([
                    'state' => ConsentState::Revoked,
                    'revoked_at' => $now,
                ]);

                Event::dispatch(new ConsentimentoRevogado(
                    tenantId: $record->tenant_id,
                    patientId: $record->patient_id,
                    consentRecordId: $record->id,
                    channel: $channel,
                    finalidade: $finalidade,
                    revokedAt: $now,
                    evidenceMessageId: $evidenceMessageId,
                    scope: $scope,
                ));

                $revoked[] = $record;
            }

            return $revoked;
        });
    }

    /**
     * Verifica se paciente tem consentimento ATIVO para uma finalidade.
     *
     * Usado por:
     *   - CampaignComplianceGate (Lote C) — bloqueia envio se marketing não consentido.
     *   - WebhookDispatcher (Lote D) — mascarar PII de paciente sem share_with_integrations.
     */
    public function hasGranted(int $patientId, ConsentFinalidade $finalidade): bool
    {
        return ConsentRecord::query()
            ->where('patient_id', $patientId)
            ->forFinalidade($finalidade)
            ->granted()
            ->exists();
    }

    /**
     * Processa comando `/sair` recebido em canal.
     *
     * Q25: default revoga APENAS marketing (preserva transacional).
     * Comando `/sair tudo` deve invocar `revoke()` separadamente para outras finalidades.
     *
     * @return list<ConsentRecord> registros revogados.
     */
    public function processSairCommand(Paciente $patient, string $channel, ?int $evidenceMessageId = null): array
    {
        return $this->revoke(
            patient: $patient,
            finalidade: ConsentFinalidade::Marketing,
            channel: $channel,
            evidenceMessageId: $evidenceMessageId,
            scope: 'all',
        );
    }
}
