<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Channel\Adapters;

use App\Domain\Messaging\Channel\Models\Channel;

/**
 * Feature 014 — Contrato complementar para provedores cujo canal conecta por
 * pareamento de sessão (QR Code), como a Evolution API. Provedores oficiais
 * (Twilio) NÃO implementam esta interface.
 *
 * Os endpoints de QR/estado só são oferecidos quando o adapter resolvido para o
 * canal `instanceof SupportsQrConnection`.
 *
 * @see specs/014-channel-provider-integration/contracts/channel-provider.md §1
 */
interface SupportsQrConnection
{
    /**
     * Cria/registra a instância no servidor do provedor e configura o webhook.
     */
    public function createInstance(Channel $channel): InstanceConnection;

    /**
     * Retorna um QR Code fresco para parear (regenera se expirado).
     */
    public function getQrCode(Channel $channel): QrPayload;

    /**
     * Estado bruto da conexão no provedor: 'open' | 'connecting' | 'close'.
     */
    public function connectionState(Channel $channel): string;

    /**
     * Encerra a sessão / remove a instância no provedor.
     */
    public function disconnect(Channel $channel): void;
}
