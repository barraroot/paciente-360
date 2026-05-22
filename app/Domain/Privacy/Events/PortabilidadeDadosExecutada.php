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
 * **T048 (Fase 8 — Lote A US-13.2)** — Arquivo JSON de portabilidade gerado (Q28).
 *
 * Payload NÃO contém o `file_signed_url_id` (UUID público) — apenas o
 * `portability_request_id`. URL assinada vai ao paciente via e-mail
 * separado (não cabe ao audit log expor).
 */
final class PortabilidadeDadosExecutada implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $patientId,
        public readonly int $portabilityRequestId,
        public readonly Carbon $executedAt,
        public readonly int $executedByUserId,
        public readonly int $fileSizeBytes,
        public readonly string $schemaVersion,
    ) {}

    public function auditAction(): string
    {
        return 'portability.executed';
    }

    public function auditPayload(): array
    {
        return [
            'portability_request_id' => $this->portabilityRequestId,
            'executed_at' => $this->executedAt->toIso8601String(),
            'executed_by_user_id' => $this->executedByUserId,
            'file_size_bytes' => $this->fileSizeBytes,
            'schema_version' => $this->schemaVersion,
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
        return $this->executedByUserId;
    }
}
