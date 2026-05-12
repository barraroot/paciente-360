<?php

namespace App\Domain\Messaging\Channel\Adapters;

/**
 * T048 — DTO de payload de mídia para mensagens outbound.
 *
 * Placeholder simples — implementação completa (upload S3, validação MIME,
 * antivirus scan) vem no Lote D.
 *
 * @see OutboundMessage
 */
final readonly class MediaPayload
{
    public function __construct(
        public string $mimeType,
        public string $storagePath,
        public int $sizeBytes,
        public ?string $originalFilename = null,
    ) {}
}
