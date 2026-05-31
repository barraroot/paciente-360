<?php

declare(strict_types=1);

namespace App\Jobs\Ai;

use App\Domain\Ai\Coalescing\Events\TurnCoalesced;
use App\Domain\Ai\Coalescing\Events\TurnDispatched;
use App\Domain\Ai\Coalescing\Services\ConversationTurnCoordinator;
use App\Domain\Ai\Coalescing\Services\PassiveDebounceScheduler;
use App\Domain\Ai\Coalescing\Services\TurnState;
use App\Domain\Ai\Coalescing\Services\TurnVersionGuard;
use App\Domain\Ai\Services\AiMessageProcessor;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Tenant;
use App\Support\Metrics\AiMetricsContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Processa a resposta da IA de forma assíncrona (fila `ai`) — não bloqueia o
 * webhook (FR-028 Fase 17). Em falha, re-tenta com backoff; ao esgotar,
 * `failed()` marca a conversa em erro e escala para humano (FR-030c).
 *
 * **US1 (Fase 18 — FR-002 cancel-and-reprocess)**: aceita `$turnVersion` do
 * `FlushCoalescedTurnJob`. ANTES do dispatch outbound, verifica
 * `TurnVersionGuard::assertCurrent()` — se uma nova mensagem chegou durante
 * o processamento (versão atual > versão do job), DESCARTA o rascunho e
 * incrementa `reprocess`. Se ainda <= cap → re-agenda flush. Se >= cap →
 * processa com o que tem (FR-004).
 *
 * Idempotência (Cache lock por `(conv, version)`): evita 2 workers
 * processarem o mesmo turn_version simultaneamente.
 *
 * Caminho legacy (`turnVersion=null`): chamadas vindas de fluxos antigos
 * (`ReprocessLastInboundOnAiResume`) passam direto sem coalescência.
 */
final class ProcessAiResponseJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public function __construct(
        public readonly int $conversationId,
        public readonly int $tenantId,
        public readonly ?int $turnVersion = null,
    ) {
        $this->onQueue(config('ai.matricial.queue', 'ai'));
        $this->tries = (int) config('ai.matricial.retry.attempts', 3);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return (array) config('ai.matricial.retry.backoff', [10, 30, 60]);
    }

    public function handle(
        AiMessageProcessor $processor,
        ConversationTurnCoordinator $coordinator,
        TurnVersionGuard $guard,
        PassiveDebounceScheduler $scheduler,
        AiMetricsContract $metrics,
    ): void {
        $conversation = $this->resolveConversation();
        if ($conversation === null) {
            return;
        }

        // Caso legacy (turnVersion=null) — fluxos antigos não passam por
        // coalescência (ReprocessLastInboundOnAiResume etc).
        if ($this->turnVersion === null) {
            $processor->process($conversation);

            return;
        }

        // Idempotência (FR-008): evita 2 workers rodando o mesmo turn_version.
        $lockKey = "ai:turn:job:{$this->conversationId}:{$this->turnVersion}";
        if (! Cache::add($lockKey, true, 60)) {
            return;
        }

        // Cancel-and-reprocess pré-processamento (FR-002): se a versão mudou
        // entre o enfileiramento e a execução, nova(s) mensagem(s) chegaram.
        if (! $guard->assertCurrent($this->conversationId, $this->turnVersion)) {
            $this->handleSuperseded($coordinator, $scheduler, $metrics);

            return;
        }

        $state = new TurnState(
            conversationId: $this->conversationId,
            version: $this->turnVersion,
            messageIds: $coordinator->pendingMessageIds($this->conversationId),
            isFirstOfTurn: false,
            startedAt: $coordinator->startedAt($this->conversationId),
            reprocessCount: $coordinator->reprocessCount($this->conversationId),
        );

        // Determina motivo do flush para auditoria/métrica.
        $flushReason = $state->exceededMaxReprocesses()
            ? 'max_reprocesses_reached'
            : ($state->exceededMaxTurnSeconds() ? 'max_turn_seconds_reached' : 'dispatching');

        // Processa a IA (AiMessageProcessor já lê todas as msgs via histórico Fase 17).
        $processor->process($conversation);

        // Re-check pós-processamento: race janela curta entre o pensar e o dispatch
        // interno do AiMessageProcessor. Se a versão mudou agora E ainda < cap →
        // re-agenda novo turno com a versão atualizada.
        if (! $guard->assertCurrent($this->conversationId, $this->turnVersion)
            && ! $state->exceededMaxReprocesses()) {
            $coordinator->incrementReprocess($this->conversationId);
            $scheduler->scheduleFlush(
                conversationId: $this->conversationId,
                tenantId: $this->tenantId,
                turnVersion: $coordinator->currentVersion($this->conversationId),
            );

            return;
        }

        // Dispatch confirmado — emite auditoria + limpa estado.
        $this->emitAudit($state, $flushReason, $metrics);
        $coordinator->resetAfterDispatch($this->conversationId);
    }

    public function failed(Throwable $exception): void
    {
        $conversation = $this->resolveConversation();
        if ($conversation === null) {
            return;
        }

        app(AiMessageProcessor::class)->markFailed($conversation, class_basename($exception));
    }

    private function handleSuperseded(
        ConversationTurnCoordinator $coordinator,
        PassiveDebounceScheduler $scheduler,
        AiMetricsContract $metrics,
    ): void {
        Log::info('ai.turn.superseded', [
            'conversation_id' => $this->conversationId,
            'job_version' => $this->turnVersion,
            'current_version' => $coordinator->currentVersion($this->conversationId),
        ]);

        $reprocesses = $coordinator->reprocessCount($this->conversationId);
        $cap = (int) config('ai.matricial.coalesce.max_reprocesses', 3);

        if ($reprocesses >= $cap) {
            // Cap atingido — força flush imediato com o que tem (FR-004).
            $newVersion = $coordinator->currentVersion($this->conversationId);
            self::dispatch($this->conversationId, $this->tenantId, $newVersion);

            return;
        }

        $coordinator->incrementReprocess($this->conversationId);
        $metrics->coalesceReprocessCount($this->tenantId, $reprocesses + 1);

        // Re-agenda flush — quando o paciente parar de mandar, dispara.
        $scheduler->scheduleFlush(
            conversationId: $this->conversationId,
            tenantId: $this->tenantId,
            turnVersion: $coordinator->currentVersion($this->conversationId),
        );
    }

    private function emitAudit(
        TurnState $state,
        string $flushReason,
        AiMetricsContract $metrics,
    ): void {
        event(new TurnCoalesced(
            tenantId: $this->tenantId,
            conversationId: $this->conversationId,
            turnVersion: (int) $this->turnVersion,
            messageIds: $state->messageIds,
            reprocessCount: $state->reprocessCount,
            flushReason: $flushReason,
        ));

        event(new TurnDispatched(
            tenantId: $this->tenantId,
            conversationId: $this->conversationId,
            turnVersion: (int) $this->turnVersion,
            messageCount: count($state->messageIds),
            reprocessCount: $state->reprocessCount,
        ));

        $metrics->coalesceMessagesPerTurn($this->tenantId, count($state->messageIds));
        $metrics->coalesceReprocessCount($this->tenantId, $state->reprocessCount);
        $metrics->coalesceFlush($this->tenantId, $flushReason);
    }

    private function resolveConversation(): ?Conversation
    {
        $tenant = Tenant::find($this->tenantId);
        if ($tenant === null) {
            return null;
        }

        app()->instance('tenant', $tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        return Conversation::find($this->conversationId);
    }
}
