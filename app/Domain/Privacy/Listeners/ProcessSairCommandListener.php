<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Listeners;

use App\Domain\Privacy\Models\ConsentFinalidade;
use App\Domain\Privacy\Services\ConsentService;
use App\Models\Paciente;
use Illuminate\Support\Facades\Log;

/**
 * **T027 (Fase 8 — Lote A US-13.1)** — Detecta comandos `/sair` em mensagens
 * recebidas e revoga consentimento granular conforme Q25.
 *
 * **Disparo**: este listener é registrado MANUALMENTE em `Event::listen()`
 * via `AppServiceProvider` apontando para o evento `MensagemRecebida` da Fase 3.
 * NÃO usar auto-discovery aqui porque o nome do evento da Fase 3 segue
 * convenção própria (não está em `App\Domain\Privacy\Events\`).
 *
 * **Regras (Q25)**:
 *   - `/sair` → revoga apenas MARKETING (preserva transacional).
 *   - `/sair tudo` → revoga MARKETING + transacional + pesquisa (loops sobre todas).
 *
 * **MVP — estado**: a integração real com `MensagemRecebida` será feita
 * no Lote C (T149 — `tests/Feature/Campaigns/SairCommandRevokesMarketingTest`).
 * Aqui exponhamos apenas o método público `process()` que recebe paciente +
 * texto da mensagem; o listener real chamará este método.
 *
 * @see App\Domain\Privacy\Services\ConsentService::processSairCommand()
 */
final class ProcessSairCommandListener
{
    public function __construct(
        private readonly ConsentService $consentService,
    ) {}

    /**
     * Processa o corpo da mensagem e revoga consentimentos conforme padrão.
     *
     * Aceita variantes case-insensitive: `/sair`, `/SAIR`, `/sair tudo`, etc.
     * Espaços em volta e trailing são tolerados.
     *
     * @return list<int> IDs dos consent_records revogados (vazio se nenhum match).
     */
    public function process(Paciente $patient, string $channel, string $messageBody, ?int $messageId = null): array
    {
        $normalized = trim(strtolower($messageBody));

        if (! str_starts_with($normalized, '/sair')) {
            return [];
        }

        $isRevokeAll = str_contains($normalized, 'tudo');

        $revokedIds = [];

        $marketingRevoked = $this->consentService->revoke(
            patient: $patient,
            finalidade: ConsentFinalidade::Marketing,
            channel: $channel,
            evidenceMessageId: $messageId,
            scope: 'all',
        );
        $revokedIds = array_merge($revokedIds, array_map(fn ($r) => $r->id, $marketingRevoked));

        if ($isRevokeAll) {
            foreach ([ConsentFinalidade::Transacional, ConsentFinalidade::Pesquisa] as $extra) {
                $extraRevoked = $this->consentService->revoke(
                    patient: $patient,
                    finalidade: $extra,
                    channel: $channel,
                    evidenceMessageId: $messageId,
                    scope: 'all',
                );
                $revokedIds = array_merge($revokedIds, array_map(fn ($r) => $r->id, $extraRevoked));
            }
        }

        Log::info('privacy.sair_command.processed', [
            'tenant_id' => $patient->tenant_id,
            'patient_id' => $patient->id,
            'channel' => $channel,
            'message_id' => $messageId,
            'is_revoke_all' => $isRevokeAll,
            'revoked_record_ids' => $revokedIds,
        ]);

        return $revokedIds;
    }
}
