<?php

declare(strict_types=1);

namespace App\Services\Panel\DataObjects;

use Illuminate\Support\Carbon;

/**
 * **T034 (Fase 10 — Spec 010 / US-3)** — DTO de item da seção "Alertas".
 *
 * Lista heterogênea: cada item carrega seu `type` (discriminador) +
 * `severity` para ordenação determinística.
 *
 * @see specs/010-dashboard-home/research.md R6
 */
final class AttentionItemDto
{
    /** Severities ordenadas por urgência (maior número = maior urgência). */
    private const SEVERITY_RANK = [
        'info' => 1,
        'warn' => 2,
        'danger' => 3,
    ];

    public function __construct(
        public readonly string $type,
        public readonly string $severity,
        public readonly string $titleKey,
        public readonly string $description,
        public readonly string $link,
        public readonly Carbon $occurredAt,
    ) {}

    public function severityRank(): int
    {
        return self::SEVERITY_RANK[$this->severity] ?? 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'severity' => $this->severity,
            'title_key' => $this->titleKey,
            'description' => $this->description,
            'link' => $this->link,
            'occurred_at' => $this->occurredAt->toIso8601String(),
        ];
    }
}
