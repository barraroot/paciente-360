<?php

namespace App\Console\Commands;

use App\Jobs\Users\PurgeExpiredInvitationsJob;
use Illuminate\Console\Command;

/**
 * Comando que dispara o job de limpeza de convites expirados (T245).
 *
 * Agendado em `routes/console.php` semanalmente aos domingos às 03:00.
 */
final class PurgeExpiredInvitations extends Command
{
    protected $signature = 'invitations:purge-expired';

    protected $description = 'Remove convites expirados há mais de 30 dias da base de dados.';

    public function handle(): int
    {
        PurgeExpiredInvitationsJob::dispatch();

        $this->info('Job de purga de convites expirados despachado.');

        return self::SUCCESS;
    }
}
