<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Models;

/**
 * **T022 (Fase 8 — Lote A US-13.1)** — Enum tipado para `consent_records.state`.
 */
enum ConsentState: string
{
    case Granted = 'granted';
    case Refused = 'refused';
    case Revoked = 'revoked';
}
