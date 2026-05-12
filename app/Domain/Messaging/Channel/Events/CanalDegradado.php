<?php

namespace App\Domain\Messaging\Channel\Events;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T067 — Evento disparado quando a qualidade do canal cai (low/flagged).
 *
 * Action `channel.degraded`. Auditado via PersistAuditLogListener.
 */
final class CanalDegradado implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Channel $channel,
        public readonly ?string $ratingAnterior = null,
        public readonly ?string $ratingNovo = null,
    ) {}

    public function auditAction(): string
    {
        return 'channel.degraded';
    }

    public function auditableModel(): ?Model
    {
        return $this->channel;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'canal_id' => $this->channel->id,
            'rating_anterior' => $this->ratingAnterior,
            'rating_novo' => $this->ratingNovo,
        ];
    }

    public function auditTenantId(): ?int
    {
        return $this->channel->tenant_id;
    }
}
