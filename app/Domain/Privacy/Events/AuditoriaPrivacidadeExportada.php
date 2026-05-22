<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * **T073 (Fase 8 — Lote A US-13.3)** — Exportação de auditoria de privacidade.
 *
 * Emitido quando Admin Clínica usa o botão "Exportar registros" no painel de
 * privacidade (AC-13.1.6 / AC-13.3.5). Payload tem `patient_ids_count` mas
 * NÃO os IDs em si — auditoria sem expor PII residual nos logs.
 */
final class AuditoriaPrivacidadeExportada implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $exportedByUserId,
        public readonly Carbon $exportedAt,
        public readonly string $scope, // 'consents' | 'forgetting' | 'portability' | 'audit'
        public readonly int $patientIdsCount,
        public readonly string $format, // 'json' | 'csv'
    ) {}

    public function auditAction(): string
    {
        return 'privacy.audit_exported';
    }

    public function auditPayload(): array
    {
        return [
            'scope' => $this->scope,
            'format' => $this->format,
            'patient_ids_count' => $this->patientIdsCount,
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
