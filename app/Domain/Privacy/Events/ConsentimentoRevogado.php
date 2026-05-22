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
 * **T025 (Fase 8 — Lote A US-13.1)** — Emitido quando paciente revoga consentimento
 * previamente concedido.
 *
 * Q25 — revogação de `/sair` é granular: revoga **apenas** marketing por default.
 * Comando `/sair tudo` ou formulário de privacidade pode revogar transacional.
 *
 * Listener {@see App\Domain\Campaigns\Listeners} (Lote C) consome este evento
 * para retirar paciente de campanhas em fila no próximo dispatch.
 */
final class ConsentimentoRevogado implements Auditable, ContainsNoClinicalData
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
        public readonly Carbon $revokedAt,
        public readonly ?int $evidenceMessageId,
        public readonly string $scope, // 'channel' = só esse canal | 'all' = todos canais
    ) {}

    public function auditAction(): string
    {
        return 'consent.revoked';
    }

    public function auditPayload(): array
    {
        return [
            'consent_record_id' => $this->consentRecordId,
            'channel' => $this->channel,
            'finalidade' => $this->finalidade->value,
            'revoked_at' => $this->revokedAt->toIso8601String(),
            'evidence_message_id' => $this->evidenceMessageId,
            'scope' => $this->scope,
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
