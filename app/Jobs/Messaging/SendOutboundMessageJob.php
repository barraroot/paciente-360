<?php

namespace App\Jobs\Messaging;

use App\Domain\Messaging\Channel\Adapters\ChannelAdapterResolver;
use App\Domain\Messaging\Channel\Adapters\OutboundMessage;
use App\Domain\Messaging\Message\Models\Message;
use App\Jobs\TenantAwareJob;
use App\Support\Metrics\MessagingMetricsContract;
use Illuminate\Support\Facades\Log;

/**
 * T111 — Job de envio real de mensagem outbound ao provider.
 *
 * Carrega a Message persistida, resolve o adapter pelo tipo de canal e
 * invoca o provider. Atualiza status da mensagem (sent/failed).
 *
 * Idempotente: se a mensagem já estiver com status 'sent', retorna sem reprocessar.
 *
 * T270 — métricas Prometheus:
 *  - `paciente360_outbound_message_total{provider, status='sent'}` — sucesso
 *  - `paciente360_outbound_message_total{provider, status='failed'}` — falha
 */
final class SendOutboundMessageJob extends TenantAwareJob
{
    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 30, 300];

    public function __construct(
        public readonly int $messageId,
    ) {
        parent::__construct();
    }

    protected function run(): void
    {
        /** @var Message|null $message */
        $message = Message::with(['conversation.channel'])->find($this->messageId);

        if ($message === null) {
            Log::warning('SendOutboundMessageJob: message not found', [
                'message_id' => $this->messageId,
            ]);

            return;
        }

        // Idempotente: não reenviar mensagem já enviada
        if ($message->status === 'sent') {
            return;
        }

        $conversation = $message->conversation;
        $channel = $conversation?->channel;

        if ($conversation === null || $channel === null) {
            $message->update([
                'status' => 'failed',
                'failure_reason' => 'Conversa ou canal não encontrado.',
            ]);

            return;
        }

        $outbound = new OutboundMessage(
            conversationExternalThreadId: $conversation->external_thread_id,
            contentType: $message->content_type,
            body: $message->body,
            templateProviderId: $message->template_provider_id,
            templateVariables: is_array($message->template_variables) ? $message->template_variables : [],
            media: null,
            idempotencyKey: $message->idempotency_key ?? '',
        );

        $provider = $channel->type;
        /** @var MessagingMetricsContract $metrics */
        $metrics = app(MessagingMetricsContract::class);

        try {
            $adapter = app(ChannelAdapterResolver::class)->for($channel);
            $result = $adapter->send($channel, $outbound);

            $message->update([
                'status' => 'sent',
                'external_id' => $result->externalId,
                'sent_at' => now(),
            ]);

            $metrics->outboundMessage($provider, 'sent');

            Log::info('SendOutboundMessageJob: sent', [
                'tenant_id' => $this->tenantId,
                'message_id' => $message->id,
                'external_id' => $result->externalId,
            ]);
        } catch (\Throwable $e) {
            $message->update([
                'status' => 'failed',
                'failure_reason' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            $metrics->outboundMessage($provider, 'failed');

            Log::error('SendOutboundMessageJob: failed', [
                'tenant_id' => $this->tenantId,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
