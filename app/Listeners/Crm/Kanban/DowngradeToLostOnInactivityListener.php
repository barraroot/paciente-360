<?php

declare(strict_types=1);

namespace App\Listeners\Crm\Kanban;

use App\Domain\Crm\Kanban\Services\KanbanAutoTransitionService;
use App\Models\FunilColuna;
use App\Models\Paciente;
use App\Models\Tenant;

/**
 * **T104 (Fase 18 — US3, FR-018)** — invocado pelo comando
 * `App\Console\Commands\Kanban\DowngradeInactiveLeadsCommand` (cron diário).
 *
 * NÃO é listener de evento — é callable executado pelo command. Mantido
 * sob `app/Listeners/Crm/Kanban/` por afinidade conceitual com os outros
 * listeners de transição.
 *
 * Para cada lead em coluna não-terminal sem `last_inbound_message_at` há
 * > N dias (config `messaging.kanban.inactivity_days`, default 30) →
 * transita para 'perdido'.
 */
final class DowngradeToLostOnInactivityListener
{
    public function __construct(
        private readonly KanbanAutoTransitionService $transitions,
    ) {}

    public function runForTenant(Tenant $tenant, int $daysInactive = 30): int
    {
        app()->instance('tenant', $tenant);

        $cutoff = now()->subDays($daysInactive);

        // Coleta IDs de colunas terminais (não devem voltar a 'perdido' — já
        // estão em estado final OU foi conversão).
        $terminalIds = FunilColuna::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_terminal', true)
            ->pluck('id')
            ->all();

        $leads = Paciente::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'lead')
            ->whereNotNull('funil_coluna_atual_id')
            ->whereNotIn('funil_coluna_atual_id', $terminalIds)
            ->whereDoesntHave('conversations', function ($q) use ($cutoff): void {
                $q->where('last_inbound_message_at', '>=', $cutoff);
            })
            ->get();

        $count = 0;
        foreach ($leads as $paciente) {
            $event = $this->transitions->apply($paciente, 'inactivity', [
                'reason' => "Lead inativo por mais de {$daysInactive} dias.",
            ]);

            if ($event->applied) {
                $count++;
            }
        }

        return $count;
    }
}
