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
 * **T048 (Fase 8 — Lote A US-13.2)** — Anonimização aplicada com sucesso (AC-13.2.3).
 *
 * Payload carrega LISTAS DE CAMPOS afetados (não valores), preservando
 * auditabilidade sem expor PII residual nos logs.
 */
final class DireitoEsquecimentoExecutado implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    /**
     * @param list<string>                                                                 $fieldsAnonymized
     * @param list<string>                                                                 $fieldsDeleted
     * @param list<array{field: string, reason: string, retention_days: int}>              $fieldsPreservedReason
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $patientId,
        public readonly int $forgettingRequestId,
        public readonly Carbon $executedAt,
        public readonly int $executedByUserId,
        public readonly array $fieldsAnonymized,
        public readonly array $fieldsDeleted,
        public readonly array $fieldsPreservedReason,
    ) {}

    public function auditAction(): string
    {
        return 'forgetting.executed';
    }

    public function auditPayload(): array
    {
        return [
            'forgetting_request_id' => $this->forgettingRequestId,
            'executed_at' => $this->executedAt->toIso8601String(),
            'executed_by_user_id' => $this->executedByUserId,
            'fields_anonymized' => $this->fieldsAnonymized,
            'fields_deleted' => $this->fieldsDeleted,
            'fields_preserved_reason' => $this->fieldsPreservedReason,
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
