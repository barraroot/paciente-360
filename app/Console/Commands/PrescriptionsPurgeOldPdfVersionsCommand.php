<?php

namespace App\Console\Commands;

use App\Jobs\Prescription\PurgeOldPrescriptionPdfVersionsJob;
use Illuminate\Console\Command;

/**
 * T173 — Comando para despachar o job de purge de versões antigas de PDFs.
 *
 * Scheduled: segunda-feiras às 02:00 BRT via routes/console.php.
 */
class PrescriptionsPurgeOldPdfVersionsCommand extends Command
{
    protected $signature = 'prescriptions:purge-old-pdfs';

    protected $description = 'Purga versões antigas de PDFs de receitas não-controladas (mantém últimas 5).';

    public function handle(): int
    {
        $this->info('Despachando job de purge de PDFs antigos de receitas...');

        PurgeOldPrescriptionPdfVersionsJob::dispatch();

        $this->info('Job despachado com sucesso.');

        return self::SUCCESS;
    }
}
