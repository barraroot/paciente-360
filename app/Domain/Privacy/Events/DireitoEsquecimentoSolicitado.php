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
 * **T048 (Fase 8 — Lote A US-13.2)** — Solicitação formal de Direito ao Esquecimento.
 *
 * Emitido quando paciente submete formulário público de esquecimento OU quando
 * Admin Clínica lavra a solicitação manualmente. Aciona:
 *   - {@see App\Domain\Privacy\Listeners\EnqueueDeadlineNotificationListener}
 *     que agenda notificações em D-5 e D-2 dias úteis (Q27).
 *   - Audit log via padrão Auditable.
 */
final class DireitoEsquecimentoSolicitado implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $patientId,
        public readonly int $forgettingRequestId,
        public readonly Carbon $requestedAt,
        public readonly Carbon $deadlineAt,
        public readonly string $channelOfRequest,
    ) {}

    public function auditAction(): string
    {
        return 'forgetting.requested';
    }

    public function auditPayload(): array
    {
        return [
            'forgetting_request_id' => $this->forgettingRequestId,
            'requested_at' => $this->requestedAt->toIso8601String(),
            'deadline_at' => $this->deadlineAt->toIso8601String(),
            'channel_of_request' => $this->channelOfRequest,
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
