<?php

namespace App\Console\Commands;

use App\Jobs\Audit\DeleteExpiredAuditLogsJob;
use Illuminate\Console\Command;

/**
 * Comando que dispara o job de deleção física de audit logs expirados (T264).
 *
 * Agendado mensalmente em `routes/console.php` no dia 2 às 04:00 —
 * sempre APÓS o `audit:archive` para garantir defesa em profundidade
 * (registros no boundary já estão duplicados em cold antes da deleção).
 */
final class DeleteExpiredAuditLogs extends Command
{
    protected $signature = 'audit:delete-expired';

    protected $description = 'Apaga audit logs com mais de 5 anos da tabela cold (LGPD Art. 16).';

    public function handle(): int
    {
        DeleteExpiredAuditLogsJob::dispatch();

        $this->info('Job de deleção de audit logs expirados despachado.');

        return self::SUCCESS;
    }
}
