<?php

namespace App\Jobs\Messaging;

use App\Domain\Messaging\Channel\Adapters\WhatsAppCloudAdapter;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Events\ConversaCriada;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Conversation\Services\PatientResolverService;
use App\Domain\Messaging\Infrastructure\Webhook\WebhookEvent;
use App\Domain\Messaging\Message\Events\MensagemRecebida;
use App\Domain\Messaging\Message\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

/**
 * T082 — Job de processamento de mensagem inbound recebida via webhook.
 *
 * Fluxo:
 *  1. Carrega WebhookEvent e decripta payload
 *  2. Resolve Channel pelo MessagingServiceSid
 *  3. Define tenant context (Spatie PermissionRegistrar)
 *  4. Parseia payload → InboundMessageDto
 *  5. Resolve Paciente (PatientResolverService)
 *  6. Find or Create Conversation
 *  7. Cria Message
 *  8. Dispara MensagemRecebida
 *
 * Não estende TenantAwareJob porque o tenant é resolvido DENTRO do job
 * via lookup do MessagingServiceSid — o webhook não carrega tenant context.
 */
class ProcessInboundMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Número máximo de tentativas.
     */
    public int $tries = 5;

    /**
     * Backoff exponencial em segundos.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60, 120, 300];

    public function __construct(
        public readonly int $webhookEventId,
    ) {}

    /**
     * Processa a mensagem inbound.
     */
    public function handle(PatientResolverService $resolver, WhatsAppCloudAdapter $adapter): void
    {
        /** @var WebhookEvent|null $webhookEvent */
        $webhookEvent = WebhookEvent::find($this->webhookEventId);

        if ($webhookEvent === null) {
            Log::warning('ProcessInboundMessageJob: WebhookEvent not found', [
                'webhook_event_id' => $this->webhookEventId,
            ]);

            return;
        }

        // O cast 'encrypted' do WebhookEvent já decripta raw_payload_encrypted.
        // O valor retornado é o JSON string original (Crypt::encryptString(json_encode(...))
        // foi inserido via DB::table sem passar pelo cast SET, então o cast GET apenas
        // decripta e retorna a string JSON).
        $rawDecrypted = $webhookEvent->raw_payload_encrypted;
        $payload = is_array($rawDecrypted)
            ? $rawDecrypted
            : (array) json_decode((string) $rawDecrypted, true);

        // Resolve Channel com fallback chain:
        //  1. MessagingServiceSid (Twilio production)
        //  2. To (Twilio Sandbox que não emite MessagingServiceSid)
        //  3. AccountSid (último recurso quando só há 1 canal daquela account)
        $messagingServiceSid = $payload['MessagingServiceSid'] ?? null;
        $toSender = $payload['To'] ?? null;
        $accountSid = $payload['AccountSid'] ?? null;

        $channel = null;
        $resolutionPath = null;

        if (is_string($messagingServiceSid) && $messagingServiceSid !== '') {
            $channel = Channel::withoutGlobalScopes()
                ->where('type', 'whatsapp')
                ->whereJsonContains('provider_metadata->messaging_service_sid', $messagingServiceSid)
                ->first();
            $resolutionPath = $channel ? 'messaging_service_sid' : null;
        }

        if ($channel === null && is_string($toSender) && $toSender !== '') {
            $channel = Channel::withoutGlobalScopes()
                ->where('type', 'whatsapp')
                ->whereJsonContains('provider_metadata->whatsapp_sender', $toSender)
                ->first();
            $resolutionPath = $channel ? 'to_sender' : null;
        }

        if ($channel === null && is_string($accountSid) && $accountSid !== '') {
            $channel = Channel::withoutGlobalScopes()
                ->where('type', 'whatsapp')
                ->whereJsonContains('provider_metadata->account_sid', $accountSid)
                ->first();
            $resolutionPath = $channel ? 'account_sid' : null;
        }

        if ($channel === null) {
            $hints = array_filter([
                'messaging_service_sid' => $messagingServiceSid,
                'to' => $toSender,
                'account_sid' => $accountSid,
            ]);
            $hintsJson = json_encode($hints, JSON_UNESCAPED_SLASHES);
            $this->markFailed($webhookEvent, "Channel não encontrado para nenhum identificador: {$hintsJson}");

            return;
        }

        Log::info('ProcessInboundMessageJob: channel resolved', [
            'channel_id' => $channel->id,
            'tenant_id' => $channel->tenant_id,
            'resolution_path' => $resolutionPath,
        ]);

        // Atualiza webhook event com contexto do canal
        $webhookEvent->update([
            'channel_id' => $channel->id,
            'tenant_id' => $channel->tenant_id,
            'processing_started_at' => now(),
            'status' => 'processing',
        ]);

        // Define tenant context para Spatie
        app(PermissionRegistrar::class)->setPermissionsTeamId($channel->tenant_id);

        // Parseia payload para DTO normalizado
        $dto = $adapter->parseInboundWebhook($payload);

        // Resolve Paciente (pode retornar null se não encontrado ou colisão)
        $paciente = $resolver->resolveForInboundChannel($channel, $dto->externalThreadId);

        // Find or Create Conversation
        $externalThreadIdNormalized = strtolower($dto->externalThreadId);

        /** @var Conversation|null $conversation */
        $conversation = Conversation::withoutGlobalScopes()
            ->where('tenant_id', $channel->tenant_id)
            ->where('channel_id', $channel->id)
            ->where('external_thread_id', $dto->externalThreadId)
            ->first();

        $conversaFoiCriada = false;

        if ($conversation === null) {
            $conversation = Conversation::create([
                'tenant_id' => $channel->tenant_id,
                'channel_id' => $channel->id,
                'patient_id' => $paciente?->id,
                'external_thread_id' => $dto->externalThreadId,
                'status' => 'aberta',
                'opened_at' => now(),
                'priority' => 'normal',
                'received_outside_hours' => false,
                'unread_count' => 0,
            ]);

            $conversaFoiCriada = true;
        }

        // Cria Message com idempotência por external_id
        $existingMessage = Message::withoutGlobalScopes()
            ->where('external_id', $dto->externalMessageId)
            ->first();

        if ($existingMessage === null) {
            // Persiste via Eloquent para aplicar cast 'encrypted' em body
            // (Princípio I — LGPD; conteúdo de mensagem cifrado em repouso).
            // `body_searchable` e `body_preview` permanecem plain text — colunas
            // dedicadas para trigram indexing e UI preview (vide data-model R4/R5).
            /** @var Message $message */
            $message = Message::withoutGlobalScopes()->create([
                'tenant_id' => $channel->tenant_id,
                'conversation_id' => $conversation->id,
                'direction' => 'in',
                'sender_type' => 'patient',
                'body' => $dto->body,
                'body_searchable' => $dto->body,
                'body_preview' => $dto->body !== null ? mb_substr($dto->body, 0, 140) : null,
                'content_type' => $dto->contentType,
                'external_id' => $dto->externalMessageId,
                'external_metadata' => [],
                'status' => 'delivered',
                'created_at' => $dto->providerTimestamp ?? now(),
            ]);

            // Atualiza conversa
            $conversation->update([
                'last_message_at' => now(),
                'last_inbound_message_at' => now(),
                'unread_count' => ($conversation->unread_count ?? 0) + 1,
            ]);

            // Dispara eventos para mensagem nova
            if ($conversaFoiCriada) {
                event(new ConversaCriada($conversation));
            }

            event(new MensagemRecebida($message, $conversation));
        } else {
            // Mensagem duplicada: re-dispara evento para garantir notificação
            // ao inbox (webhook recebido novamente — ex.: retry do Twilio).
            event(new MensagemRecebida($existingMessage, $conversation));
        }

        // Marca webhook event como processado
        $webhookEvent->update([
            'processed_at' => now(),
            'status' => 'processed',
        ]);
    }

    /**
     * Marca o webhook event como falho.
     */
    private function markFailed(WebhookEvent $webhookEvent, string $reason): void
    {
        $webhookEvent->update([
            'status' => 'failed',
            'failure_reason' => $reason,
        ]);

        Log::error('ProcessInboundMessageJob: processamento falhou', [
            'webhook_event_id' => $webhookEvent->id,
            'reason' => $reason,
        ]);
    }
}
