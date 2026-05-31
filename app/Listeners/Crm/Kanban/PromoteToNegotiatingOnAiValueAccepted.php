<?php

declare(strict_types=1);

namespace App\Listeners\Crm\Kanban;

use App\Domain\Ai\Events\AiValueAccepted;
use App\Domain\Crm\Kanban\Services\KanbanAutoTransitionService;
use App\Models\Paciente;
use App\Models\Tenant;

/**
 * **T105 (Fase 18 — US3, FR-018)** — quando o paciente aceita o valor,
 * promove o card para 'negociando'.
 */
final class PromoteToNegotiatingOnAiValueAccepted
{
    public function __construct(
        private readonly KanbanAutoTransitionService $transitions,
    ) {}

    public function handle(AiValueAccepted $event): void
    {
        $tenant = Tenant::find($event->tenantId);
        if ($tenant === null) {
            return;
        }
        app()->instance('tenant', $tenant);

        $paciente = Paciente::query()
            ->where('tenant_id', $event->tenantId)
            ->find($event->pacienteId);

        if ($paciente === null) {
            return;
        }

        $this->transitions->apply($paciente, 'value_accepted', [
            'reason' => "Paciente sinalizou interesse em agendar (conversa #{$event->conversationId}).",
        ]);
    }
}
