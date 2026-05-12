<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Messaging\Infrastructure\Webhook\WebhookEventRecorder;
use App\Http\Controllers\Controller;
use App\Jobs\Messaging\ProcessInboundMessageJob;
use App\Support\Metrics\MessagingMetricsContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * T192 — Webhook controller para Instagram Direct via Meta Graph API.
 *
 * Duas actions:
 *  - `verify` (GET) : handshake inicial da Meta — responde com o challenge em plain text.
 *  - `__invoke` (POST): inbound de DMs — assinatura já validada pelo middleware.
 *
 * Princípio I — Segurança: middleware `ValidateMetaSignature` valida HMAC antes.
 * Princípio VII — Idempotência: `WebhookEventRecorder` com UNIQUE (provider, external_id).
 *
 * @see ValidateMetaSignature
 * @see WebhookEventRecorder
 * @see specs/003-omnichannel-inbox/research.md — R3 (Meta Graph API direta)
 */
class MetaInstagramWebhookController extends Controller
{
    /**
     * GET handshake — Meta confirma a URL do webhook antes de entregar eventos.
     *
     * Meta envia: hub.mode=subscribe, hub.challenge=<token>, hub.verify_token=<secret>
     * Deve responder: 200 com o challenge em PLAIN TEXT (não JSON).
     *
     * Atenção: PHP converte pontos em parâmetros de query para underscores.
     * hub.mode → hub_mode, hub.verify_token → hub_verify_token, etc.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = (string) $request->query('hub_challenge', '');

        $configuredToken = config('messaging.providers.meta.webhook_verify_token');

        if ($mode === 'subscribe' && ! empty($configuredToken) && $token === $configuredToken) {
            Log::info('MetaInstagramWebhookController: handshake verificado');

            // Response PLAIN TEXT — sem JSON envelope (requisito Meta)
            return response($challenge, 200);
        }

        Log::warning('MetaInstagramWebhookController: handshake inválido', [
            'mode' => $mode,
            'ip' => $request->ip(),
            'token_match' => $token === $configuredToken,
        ]);

        return response('forbidden', 403);
    }

    /**
     * POST inbound — processa DMs Instagram entregues pela Meta.
     *
     * O payload pode conter múltiplos entry[] e cada entry múltiplos messaging[].
     * Cada evento é gravado de forma idempotente pelo WebhookEventRecorder e
     * um job assíncrono é despachado para processar a mensagem.
     *
     * Sempre responde 200 para evitar retry imediato da Meta.
     *
     * T270 — métricas Prometheus registradas após processamento:
     *  - webhookReceived(provider, status)
     *  - webhookProcessingDuration(provider, seconds)
     */
    public function __invoke(Request $request, WebhookEventRecorder $recorder, MessagingMetricsContract $metrics): JsonResponse
    {
        $start = microtime(true);
        $payload = $request->all();

        Log::info('MetaInstagramWebhookController: inbound recebido', [
            'object' => $payload['object'] ?? 'unknown',
            'entry_count' => count($payload['entry'] ?? []),
        ]);

        $dispatched = 0;
        $duplicates = 0;

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                $messageId = $event['message']['mid'] ?? null;

                if ($messageId === null || $messageId === '') {
                    // Echo ou delivery notification — ignora
                    continue;
                }

                $row = $recorder->recordOnce(
                    provider: 'meta',
                    externalId: $messageId,
                    payload: $event,
                    signatureVerified: true,
                    eventType: 'message_received',
                );

                if ($row === null) {
                    // Evento duplicado — re-despacha job para notificar inbox (retry do Meta)
                    $existingRow = DB::table('messaging_webhook_events')
                        ->where('provider', 'meta')
                        ->where('external_id', $messageId)
                        ->first();

                    if ($existingRow !== null) {
                        ProcessInboundMessageJob::dispatch((int) $existingRow->id);
                    }

                    $duplicates++;

                    continue;
                }

                ProcessInboundMessageJob::dispatch((int) $row['id']);
                $dispatched++;
            }
        }

        $status = $duplicates > 0 && $dispatched === 0 ? 'duplicate' : 'received';
        $metrics->webhookReceived('meta', $status);
        $metrics->webhookProcessingDuration('meta', microtime(true) - $start);

        return response()->json(['ok' => true]);
    }
}
