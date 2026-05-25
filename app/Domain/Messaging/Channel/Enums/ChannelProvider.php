<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Channel\Enums;

/**
 * Feature 014 — Provedor que opera um canal de WhatsApp.
 *
 * `twilio` = WhatsApp Business Cloud (oficial, padrão). `evolution` = Evolution
 * API v2 (não oficial, Baileys, conexão por QR Code, auto-hospedado).
 *
 * @see specs/014-channel-provider-integration/data-model.md
 */
enum ChannelProvider: string
{
    case Twilio = 'twilio';
    case Evolution = 'evolution';

    /**
     * Provedor oficial (Meta-sancionado)?
     */
    public function isOfficial(): bool
    {
        return $this === self::Twilio;
    }

    /**
     * Conecta por pareamento de sessão (QR Code)?
     */
    public function usesQrConnection(): bool
    {
        return $this === self::Evolution;
    }
}
