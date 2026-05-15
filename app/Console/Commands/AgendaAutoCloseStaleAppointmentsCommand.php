<?php

namespace App\Console\Commands;

use App\Models\Agenda\Appointment;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * T109 — Auto-close de appointments sem marcação após 7d (clarify nº 14).
 *
 * Cron diário 00:30 BRT. Busca consultas scheduled/confirmed cujo starts_at
 * é > 7 dias atrás e move para `concluida_sem_registro` (terminal, sem evento).
 */
class AgendaAutoCloseStaleAppointmentsCommand extends Command
{
    protected $signature = 'agenda:auto-close-stale-appointments';

    protected $description = 'Move appointments antigos sem marcação (>7d) para concluida_sem_registro (clarify nº 14).';

    public function handle(): int
    {
        $closed = 0;

        Tenant::query()->each(function (Tenant $tenant) use (&$closed): void {
            $days = (int) ($tenant->settings['agenda']['auto_close_stale_appointments_days'] ?? 7);
            $cutoff = now()->subDays($days);

            // Sem global scope (rodando cross-tenant em CLI)
            $count = Appointment::query()
                ->withoutTenantScope()
                ->where('tenant_id', $tenant->id)
                ->whereIn('status', ['scheduled', 'confirmed'])
                ->where('starts_at', '<', $cutoff)
                ->update(['status' => 'concluida_sem_registro']);

            if ($count > 0) {
                Log::warning('agenda.auto_closed_stale', [
                    'tenant_id' => $tenant->id,
                    'count' => $count,
                    'cutoff_days' => $days,
                ]);
                $closed += $count;
            }
        });

        if ($closed > 0) {
            $this->info("Auto-closed {$closed} stale appointments.");
        }

        return self::SUCCESS;
    }
}
