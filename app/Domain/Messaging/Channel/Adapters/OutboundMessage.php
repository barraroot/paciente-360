<?php

namespace App\Domain\Messaging\Channel\Adapters;

/**
 * T048 — DTO de mensagem outbound passada para o ChannelAdapter.
 *
 * Imutável (readonly). Agnóstico de provider — cada adapter
 * mapeia para o payload específico (Twilio, Meta Graph API, etc.).
 *
 * `idempotencyKey` é obrigatório para garantir dedup em retry (research R9).
 *
 * @see ChannelAdapter::send()
 * @see specs/003-omnichannel-inbox/research.md — R2, R3, R9
 */
final readonly class OutboundMessage
{
    /**
     * @param array<string, mixed> $templateVariables
     */
    public function __construct(
        /** E.164 (WhatsApp), IGSID (Instagram) ou visitor_token (Widget) */
        public string $conversationExternalThreadId,
        /** 'text' | 'template' | 'image' | 'audio' | 'video' | 'document' */
        public string $contentType,
        public ?string $body = null,
        public ?string $templateProviderId = null,
        public array $templateVariables = [],
        public ?MediaPayload $media = null,
        public string $idempotencyKey = '',
    ) {}
}
