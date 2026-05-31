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
 * **T206 (Fase 18 — Polish, FR-008b/d)** — conversa entrou em cooldown por
 * excesso de mensagens inbound. Auditável (audit_logs) + payload sem PII
 * clínica (apenas IDs + motivo + janela).
 *
 * `limiter_key` distingue qual camada foi excedida:
 *   - `per_conversation` — 30 msgs / 10min (default)
 *   - `per_identifier`   — 100 msgs / 10min do mesmo telefone/handle
 */
final class ConversationCooldownStarted implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly string $reason,
        public readonly string $limiterKey,
        public readonly int $cooldownMinutes,
        public readonly ?string $burstLabel = null,
    ) {}

    public function auditAction(): string
    {
        return 'messaging.conversation.cooldown.started';
    }

    public function auditPayload(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'tenant_id' => $this->conversation->tenant_id,
            'reason' => $this->reason,
            'limiter_key' => $this->limiterKey,
            'cooldown_minutes' => $this->cooldownMinutes,
            'burst_label' => $this->burstLabel,
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
        return null; // disparo automático
    }
}
