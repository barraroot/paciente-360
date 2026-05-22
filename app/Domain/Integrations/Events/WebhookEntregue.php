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
 * **T196 (Fase 8 — Lote D US-11.1)** — Entrega bem-sucedida de webhook.
 */
final class WebhookEntregue implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $webhookDeliveryId,
        public readonly int $webhookEndpointId,
        public readonly string $eventType,
        public readonly int $httpCode,
        public readonly int $durationMs,
        public readonly int $attempts,
    ) {}

    public function auditAction(): string
    {
        return 'webhook.delivered';
    }

    public function auditPayload(): array
    {
        return [
            'webhook_delivery_id' => $this->webhookDeliveryId,
            'webhook_endpoint_id' => $this->webhookEndpointId,
            'event_type' => $this->eventType,
            'http_code' => $this->httpCode,
            'duration_ms' => $this->durationMs,
            'attempts' => $this->attempts,
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
