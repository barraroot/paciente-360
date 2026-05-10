<?php

namespace App\Jobs\Tenant;

use App\Events\Tenant\TenantRestrictionsApplied;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;

/**
 * Job que verifica e aplica restrições em tenant inadimplente (T187).
 *
 * Regra: se `status = 'overdue'` e `overdue_since + 7 dias` já passou
 * e `restrictions_applied_at` ainda é null, aplica as restrições.
 *
 * Disparado pelo `ApplyOverdueRestrictions` command (schedule diário 02:00).
 *
 * Não estende `TenantAwareJob` pois é disparado pelo CLI (sem tenant no
 * container). Re-hidrata o tenant diretamente via `$tenantId`.
 */
final class ApplyOverdueRestrictionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $tenantId,
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::query()
            ->withoutGlobalScopes()
            ->findOrFail($this->tenantId);

        app()->instance('tenant', $tenant);

        if (
            $tenant->status === 'overdue'
            && $tenant->overdue_since !== null
            && $tenant->overdue_since->copy()->addDays(7)->isPast()
            && $tenant->restrictions_applied_at === null
        ) {
            $tenant->restrictions_applied_at = now();
            $tenant->save();

            Event::dispatch(new TenantRestrictionsApplied($tenant));
        }
    }
}
