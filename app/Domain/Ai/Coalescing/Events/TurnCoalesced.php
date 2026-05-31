<?php

declare(strict_types=1);

namespace App\Domain\Ai\Coalescing\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * **T072 (Fase 18 — US1, FR-005)** — turno coalescido, prestes a dispatchar.
 *
 * Emitido pelo `ProcessAiResponseJob` antes do dispatch outbound efetivo;
 * carrega o conjunto exato de mensagens que serão consideradas pela IA
 * e o motivo do flush.
 *
 * `flush_reason` ∈ {
 *   `passive_debounce_elapsed`,    // fim da janela de debounce
 *   `max_turn_seconds_reached`,    // teto absoluto FR-003
 *   `max_reprocesses_reached`,     // teto FR-004
 *   `dispatching`,                 // dispatch normal
 * }
 */
final class TurnCoalesced implements Auditable
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    /**
     * @param list<int> $messageIds
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $conversationId,
        public readonly int $turnVersion,
        public readonly array $messageIds,
        public readonly int $reprocessCount,
        public readonly string $flushReason,
    ) {}

    public function auditAction(): string
    {
        return 'ai.turn.coalesced';
    }

    public function auditPayload(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'turn_version' => $this->turnVersion,
            'message_count' => count($this->messageIds),
            'reprocess_count' => $this->reprocessCount,
            'flush_reason' => $this->flushReason,
        ];
    }

    public function auditableModel(): ?Model
    {
        return null;
    }

    public function auditTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function auditUserId(): ?int
    {
        return null;
    }

    public function auditActorType(): ?string
    {
        return 'system';
    }
}
