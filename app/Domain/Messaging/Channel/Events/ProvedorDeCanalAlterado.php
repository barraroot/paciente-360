<?php

namespace App\Domain\Messaging\Channel\Events;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Feature 014 — Disparado quando o provedor do canal de WhatsApp da clínica é
 * alterado (ex.: oficial Twilio → não oficial Evolution). Action
 * `channel.provider_changed`. Auditado via PersistAuditLogListener — sem segredos.
 */
final class ProvedorDeCanalAlterado implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Channel $channel,
        public readonly string $fromProvider,
        public readonly string $toProvider,
        public readonly ?int $executorId = null,
    ) {}

    public function auditAction(): string
    {
        return 'channel.provider_changed';
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
            'tipo' => $this->channel->type,
            'de_provedor' => $this->fromProvider,
            'para_provedor' => $this->toProvider,
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
