<?php

declare(strict_types=1);

namespace App\Domain\Ai\Coalescing\Services;

/**
 * **T068 (Fase 18 — US1, FR-002)** — guard de versão consumido pelo
 * `ProcessAiResponseJob` ANTES do dispatch outbound.
 *
 * Se a versão atual no Redis != versão capturada quando o job começou,
 * significa que NOVAS MENSAGENS chegaram durante o processamento — cancela
 * o disparo (descarta o rascunho) e sinaliza re-flush (cancel-and-reprocess).
 *
 * Limite de reprocessos (FR-004): {@see TurnState::exceededMaxReprocesses()}.
 */
final class TurnVersionGuard
{
    public function __construct(
        private readonly ConversationTurnCoordinator $coordinator,
    ) {}

    public function assertCurrent(int $conversationId, int $jobVersion): bool
    {
        return $this->coordinator->currentVersion($conversationId) === $jobVersion;
    }
}
