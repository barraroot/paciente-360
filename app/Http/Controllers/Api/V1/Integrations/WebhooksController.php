<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Domain\Integrations\Events\WebhookConfigurado;
use App\Domain\Integrations\Events\WebhookReagendado;
use App\Domain\Integrations\Jobs\DispatchWebhookJob;
use App\Domain\Integrations\Models\WebhookDeadLetter;
use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\CreateWebhookEndpointRequest;
use App\Http\Resources\Integrations\WebhookDeadLetterResource;
use App\Http\Resources\Integrations\WebhookDeliveryResource;
use App\Http\Resources\Integrations\WebhookEndpointResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * **T198 (Fase 8 — Lote D US-11.1)** — CRUD + operações de webhooks.
 *
 * Endpoints (autenticados + tenant.slug):
 *   GET    /integrations/webhooks                    → index
 *   POST   /integrations/webhooks                    → store (secret plaintext UMA vez)
 *   GET    /integrations/webhooks/{webhook}          → show
 *   PATCH  /integrations/webhooks/{webhook}          → update
 *   DELETE /integrations/webhooks/{webhook}          → destroy
 *   POST   /integrations/webhooks/{webhook}/pause    → pause/resume
 *   GET    /integrations/webhooks/{webhook}/deliveries → histórico
 *   GET    /integrations/webhooks/dlq                → DLQ tenant-scoped
 *   POST   /integrations/webhooks/dlq/{dlq}/resend   → reenvio manual
 */
class WebhooksController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', WebhookEndpoint::class);

        $endpoints = WebhookEndpoint::query()
            ->orderByDesc('id')
            ->paginate(20);

        return WebhookEndpointResource::collection($endpoints);
    }

    public function store(CreateWebhookEndpointRequest $request): JsonResponse
    {
        // Secret plaintext gerado pelo backend (cliente nunca define).
        $secretPlaintext = 'whsec_'.Str::random(48);

        $endpoint = WebhookEndpoint::query()->create([
            'tenant_id' => app('tenant')->id,
            'name' => $request->validated('name'),
            'url' => $request->validated('url'),
            'secret' => $secretPlaintext,
            'events_subscribed' => $request->validated('events_subscribed'),
            'is_active' => (bool) ($request->validated('is_active') ?? true),
            'created_by_user_id' => $request->user()?->id,
        ]);

        Event::dispatch(new WebhookConfigurado(
            tenantId: $endpoint->tenant_id,
            webhookEndpointId: $endpoint->id,
            url: $endpoint->url,
            eventsSubscribed: $endpoint->events_subscribed?->toArray() ?? [],
            actorUserId: $request->user()?->id,
            action: 'created',
        ));

        return (new WebhookEndpointResource($endpoint))
            ->additional(['meta' => [
                'secret_plaintext' => $secretPlaintext,
                'note' => 'Guarde este segredo agora — não será exibido novamente.',
            ]])
            ->response()
            ->setStatusCode(201);
    }

    public function show(WebhookEndpoint $webhook): WebhookEndpointResource
    {
        Gate::authorize('view', $webhook);

        return new WebhookEndpointResource($webhook);
    }

    public function update(CreateWebhookEndpointRequest $request, WebhookEndpoint $webhook): WebhookEndpointResource
    {
        Gate::authorize('update', $webhook);

        $webhook->update($request->only(['name', 'url', 'events_subscribed', 'is_active']));

        Event::dispatch(new WebhookConfigurado(
            tenantId: $webhook->tenant_id,
            webhookEndpointId: $webhook->id,
            url: $webhook->url,
            eventsSubscribed: $webhook->events_subscribed?->toArray() ?? [],
            actorUserId: $request->user()?->id,
            action: 'updated',
        ));

        return new WebhookEndpointResource($webhook);
    }

    public function destroy(Request $request, WebhookEndpoint $webhook): JsonResponse
    {
        Gate::authorize('delete', $webhook);

        $webhook->delete();

        Event::dispatch(new WebhookConfigurado(
            tenantId: $webhook->tenant_id,
            webhookEndpointId: $webhook->id,
            url: $webhook->url,
            eventsSubscribed: $webhook->events_subscribed?->toArray() ?? [],
            actorUserId: $request->user()?->id,
            action: 'deleted',
        ));

        return response()->json(['message' => 'Endpoint removido.'], 200);
    }

    public function pauseResume(Request $request, WebhookEndpoint $webhook): WebhookEndpointResource
    {
        Gate::authorize('update', $webhook);

        $webhook->update(['is_active' => ! $webhook->is_active]);

        Event::dispatch(new WebhookConfigurado(
            tenantId: $webhook->tenant_id,
            webhookEndpointId: $webhook->id,
            url: $webhook->url,
            eventsSubscribed: $webhook->events_subscribed?->toArray() ?? [],
            actorUserId: $request->user()?->id,
            action: $webhook->is_active ? 'resumed' : 'paused',
        ));

        return new WebhookEndpointResource($webhook);
    }

    public function listDeliveries(Request $request, WebhookEndpoint $webhook): AnonymousResourceCollection
    {
        Gate::authorize('view', $webhook);

        $deliveries = $webhook->deliveries()
            ->orderByDesc('id')
            ->paginate(50);

        return WebhookDeliveryResource::collection($deliveries);
    }

    public function listDeadLetter(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', WebhookEndpoint::class);

        $items = WebhookDeadLetter::query()
            ->orderByDesc('failed_at')
            ->paginate(50);

        return WebhookDeadLetterResource::collection($items);
    }

    public function resendFromDlq(Request $request, WebhookDeadLetter $dlq): JsonResponse
    {
        Gate::authorize('resendDlq', WebhookEndpoint::class);

        $delivery = WebhookDelivery::query()->create([
            'tenant_id' => $dlq->tenant_id,
            'webhook_endpoint_id' => $dlq->webhook_endpoint_id,
            'event_type' => $dlq->event_type,
            'event_id' => $dlq->event_id.'-resent-'.Str::random(8),
            'correlation_id' => $dlq->correlation_id,
            'payload' => $dlq->payload,
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempts' => 0,
            'max_attempts' => (int) config('finalization.webhook_max_retries', 5),
            'next_attempt_at' => Carbon::now(),
        ]);

        $dlq->update([
            'resent_by_user_id' => $request->user()?->id,
            'resent_at' => Carbon::now(),
        ]);

        DispatchWebhookJob::dispatch($delivery->id)->onQueue('webhooks');

        Event::dispatch(new WebhookReagendado(
            tenantId: $dlq->tenant_id,
            webhookDeadLetterId: $dlq->id,
            newDeliveryId: $delivery->id,
            resentByUserId: (int) $request->user()?->id,
        ));

        return response()->json([
            'message' => 'Reenfileirado.',
            'delivery_id' => $delivery->id,
        ], 202);
    }
}
