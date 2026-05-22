<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Events;

use App\Domain\Privacy\Models\ConsentFinalidade;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * **T025 (Fase 8 — Lote A US-13.1)** — Emitido quando paciente concede
 * consentimento (state=granted).
 *
 * Auditável (grava em audit_logs). Não contém PII clínica — implementa
 * {@see ContainsNoClinicalData} pelo princípio "qualquer evento que possa
 * eventualmente ser consumido pela IA deve ser safe-by-design" (Q29 forward-looking).
 *
 * Payload mínimo: identifiers + finalidade + timestamp + evidência.
 */
final class ConsentimentoRegistrado implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $patientId,
        public readonly int $consentRecordId,
        public readonly string $channel,
        public readonly ConsentFinalidade $finalidade,
        public readonly Carbon $grantedAt,
        public readonly ?int $evidenceMessageId,
        public readonly string $termsVersion,
    ) {}

    public function auditAction(): string
    {
        return 'consent.granted';
    }

    public function auditPayload(): array
    {
        return [
            'consent_record_id' => $this->consentRecordId,
            'channel' => $this->channel,
            'finalidade' => $this->finalidade->value,
            'granted_at' => $this->grantedAt->toIso8601String(),
            'evidence_message_id' => $this->evidenceMessageId,
            'terms_version' => $this->termsVersion,
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
