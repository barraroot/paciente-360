<?php

declare(strict_types=1);

namespace App\Events\Compliance;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * **T210 (Fase 18 — Polish, FR-055a/c)** — áudios brutos foram apagados
 * (purge LGPD-aware). Auditável; payload SEM PII (apenas counters + ids
 * técnicos).
 *
 * `reason` ∈ {`expired_no_consent`, `consent_revoked`} distingue:
 *   - cron diário apagou porque passou do prazo padrão e paciente NÃO tem
 *     consent `Transcricao`;
 *   - paciente revogou consent → purge retroativo imediato.
 */
final class AudioRawPurged implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly ?int $patientId,
        public readonly int $audioCount,
        public readonly string $reason,
    ) {}

    public function auditAction(): string
    {
        return 'compliance.audio_raw.purged';
    }

    public function auditPayload(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'patient_id' => $this->patientId,
            'audio_count' => $this->audioCount,
            'reason' => $this->reason,
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
        return null; // disparo automático ou consequência de ação do paciente
    }
}
