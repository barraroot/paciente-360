<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Services;

use App\Domain\Integrations\Jobs\DispatchWebhookJob;
use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Domain\Privacy\Models\ConsentFinalidade;
use App\Domain\Privacy\Services\ConsentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * **T192 (Fase 8 — Lote D US-11.1)** — Dispatcher de webhooks.
 *
 * Pipeline:
 *   1. Busca endpoints ativos do tenant subscritos no `event_type`.
 *   2. Aplica mascaramento por consentimento (Q17) — paciente sem
 *      `Integracoes` granted vira `<consent_withheld>`.
 *   3. Mascara campos clínicos quando o evento envolver `Prescription` controlada.
 *   4. Cria `WebhookDelivery` com idempotência via UNIQUE(endpoint, event_id).
 *   5. Enfileira `DispatchWebhookJob` na fila `webhooks`.
 */
final class WebhookDispatcher
{
    public function __construct(
        private readonly ConsentService $consentService,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(
        string $eventType,
        string $eventId,
        array $payload,
        int $tenantId,
        ?string $correlationId = null,
    ): void {
        $correlationId ??= (string) Str::uuid();

        $endpoints = WebhookEndpoint::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $e) => $e->isSubscribedTo($eventType));

        if ($endpoints->isEmpty()) {
            return;
        }

        foreach ($endpoints as $endpoint) {
            $maskedPayload = $this->applyMasking($payload, $eventType);

            $delivery = DB::transaction(function () use ($endpoint, $eventType, $eventId, $maskedPayload, $correlationId, $tenantId) {
                return WebhookDelivery::query()->firstOrCreate(
                    [
                        'webhook_endpoint_id' => $endpoint->id,
                        'event_id' => $eventId,
                    ],
                    [
                        'tenant_id' => $tenantId,
                        'event_type' => $eventType,
                        'correlation_id' => $correlationId,
                        'payload' => $maskedPayload,
                        'status' => WebhookDelivery::STATUS_PENDING,
                        'attempts' => 0,
                        'max_attempts' => (int) config('finalization.webhook_max_retries', 5),
                        'next_attempt_at' => Carbon::now(),
                    ]
                );
            });

            // Já existia (idempotência) — não re-enfileirar.
            if (! $delivery->wasRecentlyCreated) {
                Log::info('webhook.dispatch.idempotent_skip', [
                    'webhook_delivery_id' => $delivery->id,
                    'event_id' => $eventId,
                ]);

                continue;
            }

            DispatchWebhookJob::dispatch($delivery->id)->onQueue('webhooks');
        }
    }

    /**
     * Aplica mascaramento condicional (consentimento de paciente, controlados).
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function applyMasking(array $payload, string $eventType): array
    {
        // Q17 — paciente sem consentimento `Integracoes` granted.
        if (isset($payload['paciente']) && is_array($payload['paciente']) && isset($payload['paciente']['id'])) {
            $patientId = (int) $payload['paciente']['id'];

            if (! $this->consentService->hasGranted($patientId, ConsentFinalidade::Integracoes)) {
                $payload['paciente'] = [
                    'id' => '<consent_withheld>',
                    'consent' => 'withheld',
                    'note' => 'Paciente não consentiu compartilhamento com integrações externas.',
                ];
            }
        }

        // Receitas controladas — sempre mascaradas em webhook (Princípio LGPD agressivo).
        if (str_starts_with($eventType, 'prescricao.') && isset($payload['prescription'])) {
            $type = $payload['prescription']['type'] ?? null;
            if ($type === 'controlled') {
                unset($payload['prescription']['items'], $payload['prescription']['notes']);
                $payload['prescription']['masked'] = true;
            }
        }

        return $payload;
    }
}
