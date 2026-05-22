<?php

declare(strict_types=1);

namespace App\Console\Commands\Privacy;

use App\Domain\Privacy\Jobs\AuditPseudonymizationJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

/**
 * **T075 (Fase 8 — Lote A US-13.3)** — Cron semanal de auditoria de pseudonimização.
 *
 * Roda segundas-feiras 04:00 BRT (cron registrado em `routes/console.php` em T009).
 *
 * **Estratégia (Q29)**: enfileira {@see AuditPseudonymizationJob} na fila
 * `privacy` que roda o replay com amostra de 1% padrão. Job único — sample
 * randômico significa que retry duplicaria findings sem ganho de cobertura.
 *
 * Flags:
 *   --sample={N}  : sobrescreve % de amostra (default 1).
 *   --sync        : roda síncrono (sem job). Útil em CI e em `--force` manual.
 *   --force       : alias para --sync com flag visível ao operador.
 */
final class AuditPseudonymizationWeeklyCommand extends Command
{
    protected $signature = 'privacy:audit-pseudonymization-weekly
                            {--sample=1 : Percentual de amostra (default 1%)}
                            {--sync : Executa síncrono sem despachar job}
                            {--force : Alias para --sync com semântica explícita}';

    protected $description = 'Auditoria semanal de pseudonimização (Q29) — runtime replay de 1% dos audit_logs dos últimos 7 dias.';

    public function handle(): int
    {
        $sample = max(1, min(100, (int) $this->option('sample')));
        $sync = $this->option('sync') || $this->option('force');

        $this->info("Enqueueing pseudonymization audit (sample={$sample}%, sync=".($sync ? 'yes' : 'no').')');

        $job = new AuditPseudonymizationJob(samplePercent: $sample);

        if ($sync) {
            // Bus::dispatchSync invoca handle() na mesma request.
            Bus::dispatchSync($job);
        } else {
            // Async — fila privacy (concurrency 2).
            Bus::dispatch($job);
        }

        $this->info('Auditoria registrada em pseudonymization_audits.');

        return self::SUCCESS;
    }
}
