<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Jobs;

use App\Domain\Integrations\Events\WebhookEntregue;
use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Integrations\Services\HmacSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * **T194 (Fase 8 — Lote D US-11.1)** — Entrega assíncrona com retry policy.
 *
 * Cadência (Q16): 30s, 2min, 10min, 1h, 6h (5 tentativas total).
 * Esgotamento → enfileira `MoveToDeadLetterJob`.
 *
 * **Idempotência**: a row WebhookDelivery existe antes do job rodar — job
 * apenas atualiza atributos. Re-execução acidental é segura.
 */
final class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1; // retries são manuais (controle preciso via next_attempt_at).

    public int $timeout = 30;

    public function __construct(public int $webhookDeliveryId) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::query()->find($this->webhookDeliveryId);
        if ($delivery === null) {
            Log::warning('webhook.dispatch.delivery_not_found', ['id' => $this->webhookDeliveryId]);

            return;
        }

        $endpoint = $delivery->endpoint;
        if ($endpoint === null || ! $endpoint->is_active) {
            $delivery->update([
                'status' => WebhookDelivery::STATUS_FAILED,
                'last_error' => 'endpoint_inactive_or_deleted',
            ]);

            return;
        }

        $delivery->increment('attempts');
        $delivery->update(['status' => WebhookDelivery::STATUS_RETRYING]);

        $payloadJson = json_encode($delivery->payload?->toArray() ?? []);
        $signature = HmacSigner::sign($payloadJson, $endpoint->secret);

        $start = microtime(true);

        try {
            $response = Http::timeout((int) config('finalization.webhook_http_timeout_seconds', 10))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Paciente360-Event' => $delivery->event_type,
                    'X-Paciente360-Event-Id' => $delivery->event_id,
                    'X-Paciente360-Correlation-Id' => $delivery->correlation_id,
                    'X-Paciente360-Signature' => $signature,
                    'User-Agent' => 'Paciente360-Webhook/1.0',
                ])
                ->send('POST', $endpoint->url, ['body' => $payloadJson]);

            $durationMs = (int) ((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                $delivery->update([
                    'status' => WebhookDelivery::STATUS_DELIVERED,
                    'delivered_at' => Carbon::now(),
                    'last_response' => [
                        'http_code' => $response->status(),
                        'body_snippet' => mb_substr((string) $response->body(), 0, 500),
                        'duration_ms' => $durationMs,
                    ],
                    'last_error' => null,
                    'next_attempt_at' => null,
                ]);

                $endpoint->update([
                    'failure_count' => 0,
                    'last_success_at' => Carbon::now(),
                ]);

                Event::dispatch(new WebhookEntregue(
                    tenantId: $delivery->tenant_id,
                    webhookDeliveryId: $delivery->id,
                    webhookEndpointId: $endpoint->id,
                    eventType: $delivery->event_type,
                    httpCode: $response->status(),
                    durationMs: $durationMs,
                    attempts: $delivery->attempts,
                ));

                return;
            }

            $this->scheduleRetryOrDlq($delivery, "HTTP {$response->status()}", $response->status(), $durationMs);
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $start) * 1000);
            $this->scheduleRetryOrDlq($delivery, $e->getMessage(), 0, $durationMs);
        }
    }

    private function scheduleRetryOrDlq(WebhookDelivery $delivery, string $error, int $httpCode, int $durationMs): void
    {
        $delivery->endpoint?->increment('failure_count');
        $delivery->endpoint?->update(['last_failure_at' => Carbon::now()]);

        $delivery->update([
            'last_error' => $error,
            'last_response' => [
                'http_code' => $httpCode,
                'duration_ms' => $durationMs,
            ],
        ]);

        if ($delivery->attempts >= $delivery->max_attempts) {
            MoveToDeadLetterJob::dispatch($delivery->id)->onQueue('webhooks');

            return;
        }

        $backoff = config('finalization.webhook_retry_backoff_seconds', [30, 120, 600, 3600, 21600]);
        $delaySeconds = $backoff[$delivery->attempts - 1] ?? end($backoff);

        $delivery->update([
            'next_attempt_at' => Carbon::now()->addSeconds($delaySeconds),
        ]);

        self::dispatch($delivery->id)
            ->onQueue('webhooks')
            ->delay($delaySeconds);
    }
}
