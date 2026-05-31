<?php

declare(strict_types=1);

namespace App\Domain\Ai\Coalescing\Services;

use Illuminate\Support\Facades\Redis;

/**
 * **T066 (Fase 18 — US1, FR-001/007)** — coordenador de turno coalescido por
 * conversa, com estado Redis (versão atômica + lista de mensagens).
 *
 * Estado por conversa (chaves `ai:turn:{conv}:*` com TTL 5min):
 *  - `v`           INCR atômico — cada nova mensagem incrementa
 *  - `msgs`        LIST<message_id> — push-tailwise (ordem cronológica FR-007)
 *  - `started_at`  unix ts da 1ª msg do turno (FR-003 teto absoluto)
 *  - `reprocess`   contador de cancel-and-reprocess (FR-004 cap)
 *  - `debounce`    string-fantasma EX <s> (FR-001 fase i — debounce passivo)
 *
 * Em conjunto com {@see PassiveDebounceScheduler} (T067 — agenda flush) e
 * {@see TurnVersionGuard} (T068 — usado no dispatch para cancel-and-reprocess).
 */
final class ConversationTurnCoordinator
{
    private const KEY_PREFIX = 'ai:turn:';

    public function joinOrStartTurn(int $conversationId, int $messageId): TurnState
    {
        $key = $this->key($conversationId);
        $ttl = (int) config('ai.matricial.coalesce.redis_state_ttl_s', 300);

        // INCR atômico — primeira incrementação cria a chave.
        $version = (int) Redis::incr($key.'v');
        Redis::expire($key.'v', $ttl);

        $isFirstOfTurn = (int) Redis::llen($key.'msgs') === 0;
        Redis::rpush($key.'msgs', (string) $messageId);
        Redis::expire($key.'msgs', $ttl);

        if ($isFirstOfTurn) {
            Redis::set($key.'started_at', (string) time(), 'EX', $ttl);
            Redis::set($key.'reprocess', '0', 'EX', $ttl);
        }

        return new TurnState(
            conversationId: $conversationId,
            version: $version,
            messageIds: array_map('intval', Redis::lrange($key.'msgs', 0, -1)),
            isFirstOfTurn: $isFirstOfTurn,
            startedAt: (int) Redis::get($key.'started_at'),
            reprocessCount: (int) Redis::get($key.'reprocess'),
        );
    }

    public function currentVersion(int $conversationId): int
    {
        return (int) (Redis::get($this->key($conversationId).'v') ?? 0);
    }

    /**
     * @return list<int>
     */
    public function pendingMessageIds(int $conversationId): array
    {
        return array_map('intval', Redis::lrange($this->key($conversationId).'msgs', 0, -1));
    }

    public function startedAt(int $conversationId): int
    {
        return (int) (Redis::get($this->key($conversationId).'started_at') ?? 0);
    }

    public function reprocessCount(int $conversationId): int
    {
        return (int) (Redis::get($this->key($conversationId).'reprocess') ?? 0);
    }

    public function incrementReprocess(int $conversationId): int
    {
        return (int) Redis::incr($this->key($conversationId).'reprocess');
    }

    /**
     * Limpa o estado do turno após dispatch efetivo (sucesso ou flush por teto).
     * Mantém `v` (segue incrementando para serializar turnos seguintes).
     */
    public function resetAfterDispatch(int $conversationId): void
    {
        $key = $this->key($conversationId);
        Redis::del($key.'msgs', $key.'started_at', $key.'reprocess', $key.'debounce', $key.'dispatching');
    }

    /**
     * Lock atômico do dispatch — apenas um worker ganha (FR-008).
     * TTL curto (5s) protege contra crash sem leak.
     */
    public function acquireDispatchLock(int $conversationId, string $jobId): bool
    {
        return (bool) Redis::set(
            $this->key($conversationId).'dispatching',
            $jobId,
            'NX',
            'EX',
            5,
        );
    }

    public function releaseDispatchLock(int $conversationId): void
    {
        Redis::del($this->key($conversationId).'dispatching');
    }

    private function key(int $conversationId): string
    {
        return self::KEY_PREFIX.$conversationId.':';
    }
}
