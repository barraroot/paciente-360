<?php

namespace App\Domain\Messaging\Channel\Events;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T067 — Evento disparado quando um canal externo é conectado ao tenant.
 *
 * Action `channel.connected`. Auditado via PersistAuditLogListener.
 */
final class CanalConectado implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Channel $channel,
        public readonly ?int $executorId = null,
    ) {}

    public function auditAction(): string
    {
        return 'channel.connected';
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
        $credentials = is_array($this->channel->credentials_encrypted)
            ? $this->channel->credentials_encrypted
            : [];

        $accountSid = $credentials['account_sid'] ?? '';
        $maskedAccountSid = strlen($accountSid) > 8
            ? substr($accountSid, 0, 4).'****'.substr($accountSid, -4)
            : '****';

        return [
            'canal_id' => $this->channel->id,
            'tipo' => $this->channel->type,
            'executor_id' => $this->executorId,
            'external_account_id_masked' => $maskedAccountSid,
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
