<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Models;

/**
 * **T071 (Fase 8 — Lote A US-13.3)** — Modo de execução da auditoria (Q29).
 *
 * - {@see self::StaticReflection}: análise design-time via PHP Reflection sobre
 *   classes de Events listadas em `config('finalization.ai_consumed_events')`.
 * - {@see self::RuntimeReplay}: análise runtime amostrando 1% randômico dos
 *   audit_logs persistidos e aplicando `PiiDetector`.
 */
enum PseudonymizationAuditMode: string
{
    case StaticReflection = 'static_reflection';
    case RuntimeReplay = 'runtime_replay';

    public function label(): string
    {
        return match ($this) {
            self::StaticReflection => 'Estática (reflection)',
            self::RuntimeReplay => 'Runtime (replay 1%)',
        };
    }
}
