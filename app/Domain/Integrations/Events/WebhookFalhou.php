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
 * **T196 (Fase 8 — Lote D US-11.1)** — Falha definitiva (DLQ).
 *
 * Emitido após esgotar `max_attempts` retries.
 */
final class WebhookFalhou implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $webhookDeadLetterId,
        public readonly int $webhookEndpointId,
        public readonly string $eventType,
        public readonly int $totalAttempts,
        public readonly string $lastError,
    ) {}

    public function auditAction(): string
    {
        return 'webhook.failed';
    }

    public function auditPayload(): array
    {
        return [
            'webhook_dead_letter_id' => $this->webhookDeadLetterId,
            'webhook_endpoint_id' => $this->webhookEndpointId,
            'event_type' => $this->eventType,
            'total_attempts' => $this->totalAttempts,
            'last_error' => $this->lastError,
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
