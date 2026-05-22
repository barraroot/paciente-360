<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Models;

/**
 * **T043 (Fase 8 — Lote A US-13.2)** — Enum de status para `portability_requests`.
 *
 * Transições válidas:
 *   open → generating → ready → downloaded (paciente baixou)
 *   ready → expired (7 dias sem download)
 *   downloaded → expired (URL expirou mas paciente já tinha baixado)
 *   open → denied (verificação falhou)
 */
enum PortabilityStatus: string
{
    case Open = 'open';
    case Generating = 'generating';
    case Ready = 'ready';
    case Downloaded = 'downloaded';
    case Expired = 'expired';
    case Denied = 'denied';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberta',
            self::Generating => 'Gerando arquivo',
            self::Ready => 'Pronta para download',
            self::Downloaded => 'Baixada',
            self::Expired => 'URL expirada',
            self::Denied => 'Negada',
        };
    }

    public function isUrlActive(): bool
    {
        return in_array($this, [self::Ready, self::Downloaded], true);
    }
}
