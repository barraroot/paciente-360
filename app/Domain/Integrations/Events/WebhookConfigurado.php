<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * **T196 (Fase 8 — Lote D US-11.1)** — Endpoint webhook criado/editado/removido.
 *
 * Auditável; sem dados clínicos (apenas metadata do endpoint).
 */
final class WebhookConfigurado implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    /**
     * @param array<int, string> $eventsSubscribed
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $webhookEndpointId,
        public readonly string $url,
        public readonly array $eventsSubscribed,
        public readonly ?int $actorUserId,
        public readonly string $action = 'created',
    ) {}

    public function auditAction(): string
    {
        return 'webhook.'.$this->action;
    }

    public function auditPayload(): array
    {
        return [
            'webhook_endpoint_id' => $this->webhookEndpointId,
            'url' => $this->url,
            'events_subscribed' => $this->eventsSubscribed,
            'action' => $this->action,
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
        return $this->actorUserId;
    }

    public function auditActorType(): ?string
    {
        return $this->actorUserId === null ? 'system' : 'user';
    }
}
