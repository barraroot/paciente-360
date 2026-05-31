<?php

declare(strict_types=1);

namespace App\Events\Messaging\RateLimiting;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * **T206 (Fase 18 — Polish, FR-008b)** — cooldown encerrado: por expiração
 * natural (cron / lazy check no MessageObserver) ou por ação manual do
 * operador (`messaging.cooldown.manage`).
 *
 * `ended_by` ∈ {`operator`, `expired`} — auditável distinguível.
 */
final class ConversationCooldownEnded implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly string $endedBy,
        public readonly ?int $actorUserId = null,
    ) {}

    public function auditAction(): string
    {
        return 'messaging.conversation.cooldown.ended';
    }

    public function auditPayload(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'tenant_id' => $this->conversation->tenant_id,
            'ended_by' => $this->endedBy,
            'actor_user_id' => $this->actorUserId,
        ];
    }

    public function auditableModel(): ?Model
    {
        return $this->conversation;
    }

    public function auditTenantId(): ?int
    {
        return $this->conversation->tenant_id;
    }

    public function auditUserId(): ?int
    {
        return $this->actorUserId;
    }
}
