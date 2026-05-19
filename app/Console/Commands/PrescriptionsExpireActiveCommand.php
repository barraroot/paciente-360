<?php

namespace App\Console\Commands;

use App\Jobs\Prescription\ExpireActivePrescriptionsJob;
use Illuminate\Console\Command;

/**
 * T106 — Command para expirar receitas ativas vencidas.
 *
 * Despacha ExpireActivePrescriptionsJob que:
 *  - Marca receitas ativas com expires_at < today como 'superseded'.
 *  - Emite ReceitaVencida para cada receita expirada.
 */
class PrescriptionsExpireActiveCommand extends Command
{
    protected $signature = 'prescriptions:expire-active';

    protected $description = 'Marca receitas ativas com expires_at < hoje como superseded e emite ReceitaVencida.';

    public function handle(): int
    {
        dispatch(new ExpireActivePrescriptionsJob);

        $this->info('ExpireActivePrescriptionsJob despachado.');

        return self::SUCCESS;
    }
}
