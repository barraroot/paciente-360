<?php

declare(strict_types=1);

namespace App\Listeners\Crm\Kanban;

use App\Domain\Crm\Kanban\Services\LeadOnboardingService;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Events\MensagemRecebida;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\RateLimiting\BurstClassifier;
use App\Domain\Messaging\RateLimiting\CooldownService;
use App\Domain\Messaging\RateLimiting\Exceptions\RateLimitExceededException;
use App\Domain\Messaging\RateLimiting\InboundConversationLimiter;
use App\Domain\Messaging\RateLimiting\IsConversationOnCooldownChecker;
use App\Models\Tenant;

/**
 * **T083 (Fase 18 — US2, FR-009)** — Lead onboarding automático no 1º contato.
 *
 * **Polish T203 (FR-008a..d)** — antes do onboarding, aplica rate limit
 * anti-abuso (2 camadas). Se excedido, dispara cooldown auditável e PULA
 * onboarding+coalescência. A mensagem inbound segue PERSISTIDA pelo
 * `ProcessInboundMessageJob` — não é descartada (FR-008b).
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
        private readonly InboundConversationLimiter $limiter,
        private readonly CooldownService $cooldown,
        private readonly IsConversationOnCooldownChecker $cooldownChecker,
        private readonly BurstClassifier $burstClassifier,
    ) {}

    public function handle(MensagemRecebida $event): void
    {
        $message = $event->message;
        $conversation = $event->conversation;

        // Apenas mensagens recebidas do paciente (não system/user/ai).
        if ($message->direction !== 'in' || $message->sender_type !== 'patient') {
            return;
        }

        // Mensagens sandbox NÃO criam leads reais nem entram na rate limit (US6).
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

        // **Polish T203 (FR-008a/b)** — se já está em cooldown ativo, pula
        // a chamada do limiter (não conta hit) e sai cedo — IA fica pausada
        // até `cooldown_until` ou liberação manual.
        if ($this->cooldownChecker->check($conversation)) {
            return;
        }

        try {
            $this->limiter->checkOrThrow(
                conversationId: $conversation->id,
                tenantId: $tenant->id,
                identifier: $identifier,
            );
        } catch (RateLimitExceededException $e) {
            $this->startCooldown($conversation, $e->limiterKey);

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

    private function startCooldown(Conversation $conversation, string $limiterKey): void
    {
        $reason = 'rate_limit_'.$limiterKey;
        $burstLabel = $this->classifyRecentBurst($conversation);

        $this->cooldown->startFor(
            conversation: $conversation,
            reason: $reason,
            limiterKey: $limiterKey,
            burstLabel: $burstLabel,
        );
    }

    private function classifyRecentBurst(Conversation $conversation): ?string
    {
        $recent = Message::query()
            ->where('tenant_id', $conversation->tenant_id)
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'in')
            ->where('sender_type', 'patient')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        if ($recent->isEmpty()) {
            return null;
        }

        return $this->burstClassifier->classify($recent);
    }
}
