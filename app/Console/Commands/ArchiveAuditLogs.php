<?php

namespace App\Console\Commands;

use App\Jobs\Audit\ArchiveAuditLogsJob;
use Illuminate\Console\Command;

/**
 * Comando que dispara o job de arquivamento de audit logs (T264).
 *
 * Agendado mensalmente em `routes/console.php` no dia 1 às 04:00.
 */
final class ArchiveAuditLogs extends Command
{
    protected $signature = 'audit:archive';

    protected $description = 'Move audit logs com mais de 2 anos para a tabela cold (audit_logs_cold).';

    public function handle(): int
    {
        ArchiveAuditLogsJob::dispatch();

        $this->info('Job de arquivamento de audit logs despachado.');

        return self::SUCCESS;
    }
}
