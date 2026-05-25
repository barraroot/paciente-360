<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Infrastructure\Webhook\WebhookEventRecorder;
use App\Http\Controllers\Controller;
use App\Jobs\Messaging\ProcessInboundMessageJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Feature 014 — Webhook do Evolution (não oficial). Trata `connection.update`
 * (status) e `messages.upsert` (inbound → inbox).
 *
 * Segurança (Princípio VII): valida o header `apikey` contra o segredo
 * configurado e resolve o tenant SEMPRE pela instância (nunca por parâmetro
 * livre — Princípio II). Responde 200 de forma idempotente.
 */
class EvolutionWebhookController extends Controller
{
    public function __invoke(Request $request, WebhookEventRecorder $recorder, ?string $instance = null): JsonResponse
    {
        $secret = (string) config('messaging.providers.evolution.webhook_secret', '');

        if ($secret === '' || ! hash_equals($secret, (string) $request->header('apikey'))) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $instanceName = $instance ?? (string) $request->input('instance', '');
        $event = (string) $request->input('event', '');

        if ($event === 'messages.upsert') {
            return $this->handleInbound($request, $recorder, $instanceName);
        }

        if ($event === 'connection.update') {
            return $this->handleConnectionUpdate($request, $instanceName);
        }

        return response()->json(['ok' => true]);
    }

    private function handleConnectionUpdate(Request $request, string $instanceName): JsonResponse
    {
        $channel = $this->resolveChannel($instanceName);

        if ($channel === null) {
            return response()->json(['ok' => true]);
        }

        $state = (string) ($request->input('data.state', $request->input('data.connection', 'close')));
        $channel->update(['status' => Channel::mapEvolutionState($state)]);

        Log::info('webhook.evolution.connection_update', [
            'tenant_id' => $channel->tenant_id,
            'channel_id' => $channel->id,
            'state' => $state,
        ]);

        return response()->json(['ok' => true]);
    }

    private function handleInbound(Request $request, WebhookEventRecorder $recorder, string $instanceName): JsonResponse
    {
        // Ignora mensagens enviadas por nós (echo do próprio envio).
        if ($request->boolean('data.key.fromMe')) {
            return response()->json(['ok' => true, 'skipped' => 'fromMe']);
        }

        // Só processa se a instância for conhecida (Princípio II — resolve por instância).
        if ($this->resolveChannel($instanceName) === null) {
            return response()->json(['ok' => true]);
        }

        $externalId = (string) $request->input('data.key.id', '');

        if ($externalId === '') {
            return response()->json(['ok' => true]);
        }

        $row = $recorder->recordOnce(
            provider: 'evolution',
            externalId: $externalId,
            payload: $request->all(),
            signatureVerified: true,
            eventType: 'message_received',
        );

        if ($row !== null) {
            ProcessInboundMessageJob::dispatch((int) $row['id']);
        }

        return response()->json(['ok' => true]);
    }

    private function resolveChannel(string $instanceName): ?Channel
    {
        if ($instanceName === '') {
            return null;
        }

        return Channel::withoutGlobalScopes()
            ->where('provider', 'evolution')
            ->whereJsonContains('provider_metadata->instance_name', $instanceName)
            ->first();
    }
}
