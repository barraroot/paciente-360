<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Events;

use App\Domain\Privacy\Models\PseudonymizationAuditMode;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * **T073 (Fase 8 — Lote A US-13.3)** — Auditoria de pseudonimização concluída (Q29).
 *
 * Payload nunca contém o VALOR PII detectado — apenas counts e pattern names.
 * Auditável; gera entrada em audit_logs.
 */
final class PoliticaPseudonimizacaoAuditada implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $auditId,
        public readonly Carbon $auditedAt,
        public readonly PseudonymizationAuditMode $mode,
        public readonly int $totalEventsScanned,
        public readonly int $nonConformantEvents,
        public readonly ?int $auditedByUserId,
    ) {}

    public function auditAction(): string
    {
        return 'pseudonymization.audited';
    }

    public function auditPayload(): array
    {
        return [
            'audit_id' => $this->auditId,
            'audited_at' => $this->auditedAt->toIso8601String(),
            'mode' => $this->mode->value,
            'total_events_scanned' => $this->totalEventsScanned,
            'non_conformant_events' => $this->nonConformantEvents,
            'is_compliant' => $this->nonConformantEvents === 0,
        ];
    }

    public function auditableModel(): ?Model
    {
        return null;
    }

    public function auditTenantId(): ?int
    {
        return null; // GLOBAL — não tenant-scoped.
    }

    public function auditUserId(): ?int
    {
        return $this->auditedByUserId;
    }
}
