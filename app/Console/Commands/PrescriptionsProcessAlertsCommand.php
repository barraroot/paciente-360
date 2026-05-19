<?php

namespace App\Console\Commands;

use App\Jobs\Prescription\ProcessPrescriptionAlertsJob;
use Illuminate\Console\Command;

/**
 * T105 — Command para processar alertas de vencimento de receitas.
 *
 * Despacha ProcessPrescriptionAlertsJob em modo cross-tenant.
 * Com --retry-blocked: inclui alerts blocked_no_template/blocked_no_channel.
 */
class PrescriptionsProcessAlertsCommand extends Command
{
    protected $signature = 'prescriptions:process-alerts {--retry-blocked : Processa também alertas bloqueados por ausência de template/canal}';

    protected $description = 'Processa alertas de vencimento de receitas (D-15/D-7/D-1) para todos os tenants.';

    public function handle(): int
    {
        $retryBlocked = (bool) $this->option('retry-blocked');

        dispatch(new ProcessPrescriptionAlertsJob(retryBlocked: $retryBlocked));

        $msg = $retryBlocked
            ? 'ProcessPrescriptionAlertsJob despachado (incluindo alertas bloqueados).'
            : 'ProcessPrescriptionAlertsJob despachado.';

        $this->info($msg);

        return self::SUCCESS;
    }
}
