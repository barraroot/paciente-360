<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Channel\Adapters;

/**
 * Feature 014 — QR Code de pareamento retornado por um provedor com conexão
 * por sessão (Evolution). `base64` é a imagem PNG pronta para exibir; `code` é
 * a string do QR; `pairingCode` é o código alternativo (quando disponível).
 *
 * Efêmero — NÃO deve ser persistido após o pareamento (Princípio I).
 */
final readonly class QrPayload
{
    public function __construct(
        public ?string $base64 = null,
        public ?string $code = null,
        public ?string $pairingCode = null,
    ) {}
}
