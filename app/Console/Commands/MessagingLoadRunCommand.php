<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * T278 — Executa Artillery stress test scenarios para mensageria.
 *
 * Uso:
 *   `vendor/bin/sail artisan messaging:load-run --scenario=inbox-load`
 *   `vendor/bin/sail artisan messaging:load-run --scenario=webhook-flood`
 *   `vendor/bin/sail artisan messaging:load-run --scenario=outbound-burst`
 *   `vendor/bin/sail artisan messaging:load-run --scenario=reverb-broadcast`
 *
 * Requisito: Artillery instalado globalmente (`npm install -g artillery`).
 * Relatórios salvos em `storage/load-reports/{scenario}-YYYY-MM-DD-HisMs.html`
 *
 * **IMPORTANTE**: Artillery não deve rodar em CI (manual antes de go-live conforme spec § R8).
 */
class MessagingLoadRunCommand extends Command
{
    protected $signature = 'messaging:load-run {--scenario=inbox-load : Scenario name (inbox-load|webhook-flood|outbound-burst|reverb-broadcast)}';

    protected $description = 'Run Artillery stress test scenario for messaging infrastructure';

    public function handle(): int
    {
        $scenario = $this->option('scenario');
        $allowed = ['inbox-load', 'webhook-flood', 'outbound-burst', 'reverb-broadcast'];

        if (! in_array($scenario, $allowed, true)) {
            $this->error(sprintf(
                'Invalid scenario "%s". Allowed: %s',
                $scenario,
                implode(', ', $allowed)
            ));

            return 1;
        }

        // Verificar se Artillery está instalado
        $artilleryCheck = new Process(['which', 'artillery']);
        $artilleryCheck->run();

        if (! $artilleryCheck->isSuccessful()) {
            $this->error('Artillery not found. Install with: npm install -g artillery');

            return 1;
        }

        $yamlPath = base_path("tests/load/{$scenario}.yaml");

        if (! File::exists($yamlPath)) {
            $this->error("Scenario file not found: {$yamlPath}");

            return 1;
        }

        // Criar diretório de relatórios se não existir
        $reportsDir = storage_path('load-reports');
        if (! File::exists($reportsDir)) {
            File::makeDirectory($reportsDir, 0755, true);
        }

        // Gerar nome do arquivo de relatório
        $timestamp = now()->format('Y-m-d-His');
        $reportPath = "{$reportsDir}/{$scenario}-{$timestamp}.json";

        $this->info("Running Artillery scenario: {$scenario}");
        $this->info("YAML path: {$yamlPath}");
        $this->info("Report will be saved to: {$reportPath}");
        $this->newLine();

        // Executar Artillery
        $cmd = ['artillery', 'run', '--output', $reportPath, $yamlPath];

        // Se estiver em modo verbose, passar --verbose para Artillery
        if ($this->option('verbose')) {
            $cmd[] = '--verbose';
        }

        $process = new Process($cmd);

        // Streaming de output em tempo real
        $process->setTimeout(null);
        $process->run(function ($type, $buffer) {
            if ($type === Process::ERR) {
                $this->error($buffer);
            } else {
                $this->line($buffer);
            }
        });

        $exitCode = $process->getExitCode();

        if ($exitCode === 0 && File::exists($reportPath)) {
            $this->info('');
            $this->info('Report saved: '.$reportPath);
            $this->info('Generate HTML report: artillery report '.$reportPath);
            $this->info('');
            $this->comment('Next: inspect metrics in storage/load-reports/');
        }

        return $exitCode ?? 0;
    }
}
