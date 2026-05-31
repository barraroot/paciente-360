<?php

declare(strict_types=1);

namespace App\Domain\Ai\Coalescing\Services;

/**
 * DTO imutável do estado de um turno coalescido (US1).
 */
final readonly class TurnState
{
    /**
     * @param list<int> $messageIds
     */
    public function __construct(
        public int $conversationId,
        public int $version,
        public array $messageIds,
        public bool $isFirstOfTurn,
        public int $startedAt,
        public int $reprocessCount,
    ) {}

    public function elapsedSeconds(): int
    {
        return $this->startedAt > 0 ? time() - $this->startedAt : 0;
    }

    public function exceededMaxTurnSeconds(): bool
    {
        return $this->elapsedSeconds() >= (int) config('ai.matricial.coalesce.max_turn_s', 30);
    }

    public function exceededMaxReprocesses(): bool
    {
        return $this->reprocessCount >= (int) config('ai.matricial.coalesce.max_reprocesses', 3);
    }
}
