<?php

declare(strict_types=1);

namespace App\Domain\Reports\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * **T254 (Fase 8 — Lote E US-10.1)** — Exportação CSV/PDF de relatório (FR-10.3).
 *
 * Audit-only (sem listener concreto que faz work). Apenas persiste em
 * audit_logs via PersistAuditLogListener (padrão Fase 2).
 */
final class RelatorioExportado implements Auditable
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    /**
     * @param array<string, mixed> $filtersApplied
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly string $tipo,
        public readonly string $formato,
        public readonly array $filtersApplied,
        public readonly int $exportedByUserId,
        public readonly Carbon $exportedAt,
    ) {}

    public function auditAction(): string
    {
        return 'report.exported';
    }

    public function auditPayload(): array
    {
        return [
            'tipo' => $this->tipo,
            'formato' => $this->formato,
            'filters_applied' => $this->filtersApplied,
            'exported_at' => $this->exportedAt->toIso8601String(),
        ];
    }

    public function auditableModel(): ?Model
    {
        return null;
    }

    public function auditTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function auditUserId(): ?int
    {
        return $this->exportedByUserId;
    }
}
