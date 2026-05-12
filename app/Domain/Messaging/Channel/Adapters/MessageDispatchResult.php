<?php

namespace App\Domain\Messaging\Channel\Adapters;

/**
 * T048 — DTO de resultado de despacho de mensagem outbound.
 *
 * Imutável (readonly). Retornado por `ChannelAdapter::send()` após
 * a tentativa de entrega ao provider.
 *
 * `externalId` é null quando `accepted = false`.
 * `providerMetadata` contém campos específicos do provider (MessageSid, etc.)
 * para debug sem expor dados sensíveis ao domínio.
 *
 * @see ChannelAdapter::send()
 */
final readonly class MessageDispatchResult
{
    /**
     * @param array<string, mixed> $providerMetadata
     */
    public function __construct(
        public bool $accepted,
        /** MessageSid (Twilio) ou message_id (Meta Graph API) */
        public ?string $externalId = null,
        public ?string $failureReason = null,
        public array $providerMetadata = [],
    ) {}
}
