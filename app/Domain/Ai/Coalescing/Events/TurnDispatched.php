<?php

declare(strict_types=1);

namespace App\Domain\Ai\Coalescing\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * **T072 (Fase 18 — US1, FR-005)** — resposta da IA efetivamente despachada
 * após coalescência. Carrega métrica de "1 turno = 1 resposta" (SC-011).
 */
final class TurnDispatched implements Auditable
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $conversationId,
        public readonly int $turnVersion,
        public readonly int $messageCount,
        public readonly int $reprocessCount,
    ) {}

    public function auditAction(): string
    {
        return 'ai.turn.dispatched';
    }

    public function auditPayload(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'turn_version' => $this->turnVersion,
            'message_count' => $this->messageCount,
            'reprocess_count' => $this->reprocessCount,
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
