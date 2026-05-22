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
 * **T048 (Fase 8 — Lote A US-13.2)** — Solicitação de Portabilidade de Dados (Q28).
 */
final class PortabilidadeDadosSolicitada implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $patientId,
        public readonly int $portabilityRequestId,
        public readonly Carbon $requestedAt,
        public readonly Carbon $deadlineAt,
    ) {}

    public function auditAction(): string
    {
        return 'portability.requested';
    }

    public function auditPayload(): array
    {
        return [
            'portability_request_id' => $this->portabilityRequestId,
            'requested_at' => $this->requestedAt->toIso8601String(),
            'deadline_at' => $this->deadlineAt->toIso8601String(),
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
}
