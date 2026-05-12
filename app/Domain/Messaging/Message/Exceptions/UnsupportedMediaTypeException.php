<?php

namespace App\Domain\Messaging\Message\Exceptions;

use RuntimeException;

/**
 * T109 — MIME type fora da whitelist suportada.
 */
final class UnsupportedMediaTypeException extends RuntimeException
{
    public function __construct(string $mimeType = '')
    {
        $msg = $mimeType !== ''
            ? "Tipo de mídia não suportado: '{$mimeType}'."
            : 'Tipo de mídia não suportado.';

        parent::__construct($msg);
    }
}
