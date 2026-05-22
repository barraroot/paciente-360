<?php

declare(strict_types=1);

namespace App\Domain\SuperAdmin\Events;

use App\Domain\SuperAdmin\Models\AnomalyCategory;
use App\Domain\SuperAdmin\Models\AnomalySeverity;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * **T132 (Fase 8 — Lote B US-12.3)** — Anomalia detectada pelo cron (Q22 / AC-12.3.4).
 *
 * Dispara `NotifyAnomalyToSuperAdminListener` (T134) que envia inbox interna
 * + e-mail crítico quando severity=critical.
 */
final class AnomaliaDetectada implements Auditable
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    /**
     * @param array<string, mixed> $thresholdBreached
     */
    public function __construct(
        public readonly int $anomalyId,
        public readonly AnomalyCategory $categoria,
        public readonly ?int $tenantId,
        public readonly AnomalySeverity $severity,
        public readonly array $thresholdBreached,
        public readonly Carbon $detectedAt,
    ) {}

    public function auditAction(): string
    {
        return 'anomaly.detected';
    }

    public function auditPayload(): array
    {
        return [
            'anomaly_id' => $this->anomalyId,
            'categoria' => $this->categoria->value,
            'severity' => $this->severity->value,
            'threshold_breached' => $this->thresholdBreached,
            'detected_at' => $this->detectedAt->toIso8601String(),
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
        return null; // detecção é automática (cron)
    }
}
