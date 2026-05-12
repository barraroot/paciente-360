<?php

namespace App\Domain\Messaging\Channel\Adapters;

use Carbon\Carbon;

/**
 * T048 — DTO de mensagem inbound parseada do webhook do provider.
 *
 * Imutável (readonly). Cada adapter mapeia o payload bruto (Twilio, Meta, Widget)
 * para este DTO normalizado, que é consumido por `ProcessInboundMessageJob`.
 *
 * `providerTimestamp` é null quando o provider não informa o timestamp de envio
 * (Widget público, por exemplo). Nesse caso, `created_at` do registro é usado.
 *
 * Princípio I — LGPD: `body` nunca aparece em logs — mascarado pelo middleware
 * `LogStructuredRequestData` e pelo `AuditAttributesBuilder`.
 *
 * @see ChannelAdapter::parseInboundWebhook()
 * @see specs/003-omnichannel-inbox/research.md — R4 (criptografia em repouso)
 */
final readonly class InboundMessageDto
{
    /**
     * @param array<string, string> $mediaUrls
     * @param array<string, mixed> $rawProviderMetadata
     */
    public function __construct(
        /** 'twilio' | 'meta' | 'widget' */
        public string $providerType,
        /** E.164 (WhatsApp/Twilio), IGSID (Instagram), visitor_token (Widget) */
        public string $externalThreadId,
        public string $externalMessageId,
        /** 'text' | 'image' | 'audio' | 'video' | 'document' | 'template' | etc. */
        public string $contentType,
        public ?string $body = null,
        public array $mediaUrls = [],
        public array $rawProviderMetadata = [],
        public ?Carbon $providerTimestamp = null,
    ) {}
}
