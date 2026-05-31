<?php

declare(strict_types=1);

namespace App\Listeners\Crm\Kanban;

use App\Domain\Ai\Events\AiQualificationStarted;
use App\Domain\Crm\Kanban\Services\KanbanAutoTransitionService;
use App\Models\Paciente;
use App\Models\Tenant;

/**
 * **T105 (Fase 18 — US3, FR-018)** — quando a IA detecta que a qualificação
 * começou, promove o card de 'new' → 'qualificando'.
 */
final class PromoteToQualifyingOnAiQualificationStarted
{
    public function __construct(
        private readonly KanbanAutoTransitionService $transitions,
    ) {}

    public function handle(AiQualificationStarted $event): void
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

        $this->transitions->apply($paciente, 'qualification_started', [
            'reason' => "IA iniciou qualificação do lead na conversa #{$event->conversationId}.",
        ]);
    }
}
