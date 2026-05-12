<?php

namespace App\Domain\Messaging\Channel\Events;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T067 — Evento disparado quando um canal externo é desconectado do tenant.
 *
 * Action `channel.disconnected`. Auditado via PersistAuditLogListener.
 */
final class CanalDesconectado implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Channel $channel,
        public readonly ?string $motivo = null,
        public readonly ?int $executorId = null,
    ) {}

    public function auditAction(): string
    {
        return 'channel.disconnected';
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
            'motivo' => $this->motivo,
            'executor_id' => $this->executorId,
        ];
    }

    public function auditTenantId(): ?int
    {
        return $this->channel->tenant_id;
    }

    public function auditUserId(): ?int
    {
        return $this->executorId;
    }
}
