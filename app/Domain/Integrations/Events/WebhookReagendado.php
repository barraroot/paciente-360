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
 * **T196 (Fase 8 — Lote D US-11.1)** — Reenvio manual do DLQ (AC-11.1.6).
 */
final class WebhookReagendado implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $webhookDeadLetterId,
        public readonly int $newDeliveryId,
        public readonly int $resentByUserId,
    ) {}

    public function auditAction(): string
    {
        return 'webhook.resent';
    }

    public function auditPayload(): array
    {
        return [
            'webhook_dead_letter_id' => $this->webhookDeadLetterId,
            'new_delivery_id' => $this->newDeliveryId,
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
        return $this->resentByUserId;
    }

    public function auditActorType(): ?string
    {
        return 'user';
    }
}
