<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Messaging\Infrastructure\Webhook\WebhookEventRecorder;
use App\Http\Controllers\Controller;
use App\Jobs\Messaging\ProcessInboundMessageJob;
use App\Support\Metrics\MessagingMetricsContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * T078 — Webhook inbound do Twilio WhatsApp.
 *
 * Recebe mensagens inbound do WhatsApp via Twilio. Assinatura já validada
 * pelo middleware `ValidateTwilioSignature` antes de chegar aqui.
 *
 * Responde 200 sempre (Twilio retry-friendly) — erros são tratados no job.
 *
 * Princípio I — não chama a API da Meta/Twilio no ciclo de request HTTP.
 * Princípio VII — idempotente via WebhookEventRecorder (INSERT OR IGNORE).
 */
class TwilioWhatsAppWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        WebhookEventRecorder $recorder,
        MessagingMetricsContract $metrics,
    ): JsonResponse {
        $start = microtime(true);
        $messageSid = $request->input('MessageSid', '');

        Log::info('webhook.twilio.whatsapp.received', [
            'message_sid' => $messageSid,
            'messaging_service_sid' => $request->input('MessagingServiceSid'),
        ]);

        $row = $recorder->recordOnce(
            provider: 'twilio',
            externalId: $messageSid,
            payload: $request->all(),
            signatureVerified: true,
            eventType: 'message_received',
        );

        if ($row === null) {
            // Duplicata: já processado antes. Carrega o evento existente para
            // re-despachar o job (que irá re-emitir MensagemRecebida para notificar o inbox).
            // Twilio pode re-enviar webhooks em retry — o job garante idempotência da mensagem.
            $existingRow = \DB::table('messaging_webhook_events')
                ->where('provider', 'twilio')
                ->where('external_id', $messageSid)
                ->first();

            if ($existingRow !== null) {
                ProcessInboundMessageJob::dispatch((int) $existingRow->id);
            }

            $metrics->webhookReceived('twilio', 'duplicate');
            $metrics->webhookProcessingDuration('twilio', microtime(true) - $start);

            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        // Despacha job de processamento assíncrono
        ProcessInboundMessageJob::dispatch((int) $row['id']);

        $metrics->webhookReceived('twilio', 'received');
        $metrics->webhookProcessingDuration('twilio', microtime(true) - $start);

        return response()->json(['ok' => true]);
    }
}
