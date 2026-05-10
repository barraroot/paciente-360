<?php

namespace App\Console\Commands;

use App\Jobs\Tenant\ApplyOverdueRestrictionsJob;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Command diário para aplicar restrições em tenants inadimplentes (T187).
 *
 * Regra: tenants com `status='overdue'`, `overdue_since < now()-7d` e
 * `restrictions_applied_at IS NULL` entram em graceful degradation.
 *
 * Schedule: `dailyAt('02:00')` em `routes/console.php`.
 */
final class ApplyOverdueRestrictions extends Command
{
    protected $signature = 'tenants:apply-overdue-restrictions';

    protected $description = 'Aplica restrições de inadimplência em tenants overdue há mais de 7 dias.';

    public function handle(): int
    {
        $tenants = Tenant::query()
            ->withoutGlobalScopes()
            ->where('status', 'overdue')
            ->whereNull('restrictions_applied_at')
            ->where('overdue_since', '<', now()->subDays(7))
            ->get();

        $count = $tenants->count();

        if ($count === 0) {
            $this->info('Nenhum tenant elegível para restrições.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            ApplyOverdueRestrictionsJob::dispatchSync($tenant->id);
            $this->line("Restrições aplicadas ao tenant #{$tenant->id} ({$tenant->slug}).");
        }

        $this->info("{$count} tenant(s) processado(s).");

        return self::SUCCESS;
    }
}
