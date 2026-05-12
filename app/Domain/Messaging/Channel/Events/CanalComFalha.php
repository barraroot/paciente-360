<?php

namespace App\Domain\Messaging\Channel\Events;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T067 — Evento disparado quando um canal entra em estado de falha.
 *
 * Action `channel.failed`. Auditado via PersistAuditLogListener.
 */
final class CanalComFalha implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Channel $channel,
        public readonly ?string $motivo = null,
    ) {}

    public function auditAction(): string
    {
        return 'channel.failed';
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
        ];
    }

    public function auditTenantId(): ?int
    {
        return $this->channel->tenant_id;
    }
}
