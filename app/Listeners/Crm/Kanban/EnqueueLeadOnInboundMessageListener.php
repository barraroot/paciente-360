<?php

declare(strict_types=1);

namespace App\Listeners\Crm\Kanban;

use App\Domain\Crm\Kanban\Services\LeadOnboardingService;
use App\Domain\Messaging\Message\Events\MensagemRecebida;
use App\Models\Tenant;

/**
 * **T083 (Fase 18 — US2, FR-009)** — Lead onboarding automático no 1º contato.
 *
 * Escuta `MensagemRecebida` (Fase 3 — disparado pelo `ProcessInboundMessageJob`
 * ANTES dos demais listeners da IA). Garante que existe um `Paciente`
 * no tenant correspondente:
 *  - Contato novo → cria como `lead` na coluna `is_initial=true`.
 *  - Lead existente em coluna terminal → reabre (FR-013).
 *  - Paciente regular (não-lead) → NÃO entra no kanban; anexa ao prontuário
 *    (FR-011a, Q-clarify-3=B).
 *
 * Roda **antes** do `TriggerAiResponseOnInboundMessage` graças à ordem
 * alfabética que Laravel usa por default em listeners auto-discovered
 * (`Listeners\Crm\*` vem antes de `Listeners\Ai\*`). Mas mesmo se rodassem
 * em paralelo, a coalescência (US1) usa `ai:turn:*` que é independente
 * do estado do kanban.
 *
 * Sandbox + IA pausada são respeitados (US6 / FR-006).
 */
final class EnqueueLeadOnInboundMessageListener
{
    public function __construct(
        private readonly LeadOnboardingService $onboarding,
    ) {}

    public function handle(MensagemRecebida $event): void
    {
        $message = $event->message;
        $conversation = $event->conversation;

        // Apenas mensagens recebidas do paciente (não system/user/ai).
        if ($message->direction !== 'in' || $message->sender_type !== 'patient') {
            return;
        }

        // Mensagens sandbox NÃO criam leads reais (US6).
        if ((bool) ($message->sandbox ?? false)) {
            return;
        }

        $conversation->loadMissing('channel');
        $channel = $conversation->channel;
        if ($channel === null) {
            return;
        }

        // Restaura contexto de tenant (job/listener pode rodar em worker isolado).
        $tenant = Tenant::find($conversation->tenant_id);
        if ($tenant === null) {
            return;
        }
        app()->instance('tenant', $tenant);

        $channelType = (string) $channel->type;
        $identifier = (string) $conversation->external_thread_id;

        if ($identifier === '') {
            return;
        }

        $paciente = $this->onboarding->ensureFor(
            channelType: $channelType,
            identifier: $identifier,
            tenantId: $tenant->id,
        );

        // Vincula o Paciente à Conversation se ainda não está vinculado.
        if ($paciente !== null && $conversation->patient_id === null) {
            $conversation->update(['patient_id' => $paciente->id]);
        }
    }
}
