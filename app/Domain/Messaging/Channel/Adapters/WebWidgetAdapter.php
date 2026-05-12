<?php

namespace App\Domain\Messaging\Channel\Adapters;

use App\Domain\Messaging\Channel\Models\Channel;
use Illuminate\Support\Facades\Broadcast;

/**
 * T212 — Adapter para canal Widget Web.
 *
 * Canal interno — não há API externa para chamar em `send()`.
 * Mensagens do atendente chegam ao visitante via Reverb (broadcast).
 * Mensagens do visitante chegam via POST /widget/v1/{key}/messages.
 *
 * `validateCredentials()` sempre retorna true — web widget não tem creds externas;
 * a `public_key` é gerada pelo backend em `WidgetAuthService::generatePublicKey()`.
 */
final class WebWidgetAdapter implements ChannelAdapter
{
    public function getType(): string
    {
        return 'web';
    }

    /**
     * Web widget não tem credenciais externas — sempre válido.
     *
     * @param array<string, mixed> $credentials
     */
    public function validateCredentials(array $credentials): bool
    {
        return true;
    }

    /**
     * Envia mensagem outbound (atendente → visitante) via Reverb broadcast.
     *
     * O widget JS escuta o canal `widget.session.{visitor_token}` via Pusher/Reverb.
     * Não há chamada a API externa — tudo é push interno via WebSocket.
     */
    public function send(Channel $channel, OutboundMessage $message): MessageDispatchResult
    {
        $externalId = 'widget:msg:'.uniqid('', more_entropy: true);

        // Broadcast para o canal da sessão do visitante
        // O widget JS escuta `widget.session.{external_thread_id}`
        try {
            Broadcast::on("widget.session.{$message->conversationExternalThreadId}")
                ->as('message.new')
                ->with([
                    'external_id' => $externalId,
                    'content_type' => $message->contentType,
                    'body' => $message->body,
                    'direction' => 'out',
                    'sent_at' => now()->toIso8601String(),
                ])->send();
        } catch (\Exception) {
            // Reverb broadcast failure is non-fatal — message is already in DB
        }

        return new MessageDispatchResult(
            accepted: true,
            externalId: $externalId,
        );
    }

    /**
     * Parseia payload do controller widget para DTO normalizado.
     *
     * @param array<string, mixed> $payload
     */
    public function parseInboundWebhook(array $payload): InboundMessageDto
    {
        return new InboundMessageDto(
            providerType: 'widget',
            externalThreadId: (string) ($payload['visitor_token'] ?? ''),
            externalMessageId: (string) ($payload['idempotency_key'] ?? uniqid('widget_', more_entropy: true)),
            contentType: (string) ($payload['content_type'] ?? 'text'),
            body: isset($payload['body']) && $payload['body'] !== '' ? (string) $payload['body'] : null,
            mediaUrls: [],
            rawProviderMetadata: $payload,
        );
    }
}
