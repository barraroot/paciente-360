<?php

declare(strict_types=1);

namespace App\Listeners\Crm\Kanban;

use App\Domain\Ai\Assignment\Events\IAEscalouParaHumano;
use App\Domain\Crm\Kanban\Services\KanbanAutoTransitionService;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Paciente;
use App\Models\Tenant;

/**
 * **T103 (Fase 18 — US3, FR-018)** — quando a IA escala uma conversa para
 * humano (IAEscalouParaHumano da Fase 15), promove o card para 'humano'.
 */
final class PromoteToHumanOnEscalation
{
    public function __construct(
        private readonly KanbanAutoTransitionService $transitions,
    ) {}

    public function handle(IAEscalouParaHumano $event): void
    {
        $tenant = Tenant::find($event->tenantId);
        if ($tenant === null) {
            return;
        }
        app()->instance('tenant', $tenant);

        $conversation = Conversation::query()
            ->where('tenant_id', $event->tenantId)
            ->find($event->conversationId);

        if ($conversation === null || $conversation->patient_id === null) {
            return;
        }

        $paciente = Paciente::query()
            ->where('tenant_id', $event->tenantId)
            ->find($conversation->patient_id);

        if ($paciente === null) {
            return;
        }

        $this->transitions->apply($paciente, 'ai_paused_to_human', [
            'reason' => "IA escalou para humano (motivo: {$event->reason}).",
        ]);
    }
}
