<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Jobs;

use App\Domain\Integrations\Events\WebhookFalhou;
use App\Domain\Integrations\Models\WebhookDeadLetter;
use App\Domain\Integrations\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * **T195 (Fase 8 — Lote D US-11.1)** — Move delivery falha para DLQ.
 *
 * Após esgotar `max_attempts` retries. Retenção 30d (Q16) — purge via cron T202.
 */
final class MoveToDeadLetterJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(public int $webhookDeliveryId) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::query()->find($this->webhookDeliveryId);
        if ($delivery === null) {
            return;
        }

        if ($delivery->status === WebhookDelivery::STATUS_DEAD_LETTER) {
            Log::info('webhook.move_to_dlq.idempotent_skip', ['delivery_id' => $delivery->id]);

            return;
        }

        $failureHistory = [
            [
                'attempt' => $delivery->attempts,
                'http_code' => $delivery->last_response?->toArray()['http_code'] ?? null,
                'error' => $delivery->last_error,
                'occurred_at' => Carbon::now()->toIso8601String(),
            ],
        ];

        $retentionDays = (int) config('finalization.webhook_dlq_retention_days', 30);

        $dlq = WebhookDeadLetter::query()->create([
            'tenant_id' => $delivery->tenant_id,
            'webhook_endpoint_id' => $delivery->webhook_endpoint_id,
            'original_delivery_id' => $delivery->id,
            'event_type' => $delivery->event_type,
            'event_id' => $delivery->event_id,
            'correlation_id' => $delivery->correlation_id,
            'payload' => $delivery->payload,
            'failure_history' => $failureHistory,
            'failed_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addDays($retentionDays),
        ]);

        $delivery->update([
            'status' => WebhookDelivery::STATUS_DEAD_LETTER,
            'next_attempt_at' => null,
        ]);

        Event::dispatch(new WebhookFalhou(
            tenantId: $delivery->tenant_id,
            webhookDeadLetterId: $dlq->id,
            webhookEndpointId: $delivery->webhook_endpoint_id,
            eventType: $delivery->event_type,
            totalAttempts: $delivery->attempts,
            lastError: (string) $delivery->last_error,
        ));
    }
}
