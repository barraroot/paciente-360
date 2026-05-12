<?php

namespace App\Domain\Messaging\Message\Services;

use App\Domain\Messaging\Channel\Adapters\OutboundMessage;
use App\Domain\Messaging\Channel\Adapters\WhatsAppCloudAdapter;
use App\Domain\Messaging\Channel\Models\ChannelTemplate;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Events\MensagemBloqueadaForaJanela;
use App\Domain\Messaging\Message\Events\MensagemEnviada;
use App\Domain\Messaging\Message\Exceptions\WindowExpiredWithoutTemplateException;
use App\Domain\Messaging\Message\Models\Message;
use App\Jobs\Messaging\SendOutboundMessageJob;
use Illuminate\Support\Facades\Log;

/**
 * T108 — Serviço de despacho de mensagens outbound.
 *
 * Aplica o Princípio VI (janela 24h), verifica idempotência,
 * persiste a mensagem e enfileira o job de envio.
 *
 * @see specs/003-omnichannel-inbox/spec.md § FR-019 + AC-4.4.5
 */
final class MessageDispatchService
{
    public function __construct(
        private readonly WhatsAppCloudAdapter $whatsappAdapter,
    ) {}

    /**
     * Envia mensagem outbound: valida janela 24h, idempotência, persiste e enfileira.
     *
     * @throws WindowExpiredWithoutTemplateException
     * @throws \Exception Para mismatch de idempotency_key
     */
    public function send(
        Conversation $conv,
        OutboundMessage $msg,
        ?int $senderUserId = null,
    ): Message {
        $conv->loadMissing('channel');

        // --- Princípio VI runtime gate ---
        $this->enforceWindow24h($conv, $msg, $senderUserId);

        // --- Idempotência ---
        if ($msg->idempotencyKey !== '') {
            $existing = Message::withoutGlobalScopes()
                ->where('tenant_id', $conv->tenant_id)
                ->where('idempotency_key', $msg->idempotencyKey)
                ->first();

            if ($existing !== null) {
                // Mesma key mas body diferente → erro
                if ($existing->body !== $msg->body) {
                    throw new \Exception('Idempotency key conflict: body mismatch.');
                }

                return $existing;
            }
        }

        // --- Persistir mensagem ---
        $body = $msg->body;
        $preview = $body !== null ? mb_substr($body, 0, 140) : null;

        $message = Message::create([
            'tenant_id' => $conv->tenant_id,
            'conversation_id' => $conv->id,
            'direction' => 'out',
            'sender_type' => 'user',
            'sender_id' => $senderUserId,
            'body' => $body,
            'body_searchable' => $body !== null ? mb_strtolower($body) : null,
            'body_preview' => $preview,
            'content_type' => $msg->contentType,
            'template_provider_id' => $msg->templateProviderId,
            'template_variables' => $msg->templateVariables !== [] ? $msg->templateVariables : null,
            'idempotency_key' => $msg->idempotencyKey !== '' ? $msg->idempotencyKey : null,
            'status' => 'queued',
            'external_metadata' => [],
        ]);

        // --- Enfileirar job de envio ---
        SendOutboundMessageJob::dispatch($message->id)->onQueue('outbound-messages');

        // --- Atualizar conversa ---
        $newStatus = $conv->status === 'aberta' ? 'pendente' : $conv->status;
        $conv->update([
            'last_message_at' => now(),
            'unread_count' => 0,
            'status' => $newStatus,
        ]);

        // --- Disparar evento ---
        event(new MensagemEnviada($message, $conv));

        Log::info('message.dispatch.queued', [
            'tenant_id' => $conv->tenant_id,
            'conversation_id' => $conv->id,
            'message_id' => $message->id,
            'user_id' => $senderUserId,
        ]);

        return $message;
    }

    /**
     * Princípio VI — verifica se pode enviar fora da janela de 24h.
     *
     * @throws WindowExpiredWithoutTemplateException
     */
    private function enforceWindow24h(
        Conversation $conv,
        OutboundMessage $msg,
        ?int $senderUserId,
    ): void {
        $channelType = $conv->channel?->type ?? '';

        // Web não tem restrição 24h
        if ($channelType === 'web') {
            return;
        }

        // Templates são sempre permitidos
        if ($msg->contentType === 'template') {
            return;
        }

        $lastInbound = $conv->last_inbound_message_at;

        // Sem inbound ainda: bloqueia (sem janela aberta, precisa template)
        if ($lastInbound === null) {
            $this->auditAndThrow($conv, $lastInbound, 0.0, $senderUserId);
        }

        $hours = $lastInbound->diffInMinutes(now()) / 60;

        if ($hours >= 24) {
            $this->auditAndThrow($conv, $lastInbound, round($hours, 1), $senderUserId);
        }
    }

    /**
     * Registra audit e lança exceção de janela expirada.
     *
     * @throws WindowExpiredWithoutTemplateException
     */
    private function auditAndThrow(
        Conversation $conv,
        mixed $lastInbound,
        float $hours,
        ?int $senderUserId,
    ): never {
        $availableTemplatesCount = ChannelTemplate::withoutGlobalScopes()
            ->where('channel_id', $conv->channel_id)
            ->where('meta_template_status', 'approved')
            ->count();

        event(new MensagemBloqueadaForaJanela(
            conversation: $conv,
            lastInboundMessageAt: $lastInbound,
            hoursSinceLastInbound: $hours,
            executorId: $senderUserId,
        ));

        throw new WindowExpiredWithoutTemplateException(
            lastInboundMessageAt: $lastInbound,
            hoursSinceLastInbound: $hours,
            availableTemplatesCount: $availableTemplatesCount,
        );
    }
}
