<?php

declare(strict_types=1);

namespace App\Domain\SuperAdmin\Models;

/**
 * **T131 (Fase 8 — Lote B US-12.3)** — Severidade de anomalia.
 *
 * `critical` aciona e-mail crítico além de inbox interna (Q22).
 */
enum AnomalySeverity: string
{
    case Warning = 'warning';
    case Critical = 'critical';

    public function isCritical(): bool
    {
        return $this === self::Critical;
    }
}
