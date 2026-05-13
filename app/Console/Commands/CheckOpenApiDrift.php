<?php

namespace App\Console\Commands;

use App\Services\OpenApi\DriftChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Detecta drift entre rotas reais e o contrato OpenAPI.
 *
 * Usa `DriftChecker` para a lógica de comparação, permitindo testes
 * unitários independentes do framework.
 *
 * @see App\Services\OpenApi\DriftChecker
 * @see scripts/check-openapi.php
 */
final class CheckOpenApiDrift extends Command
{
    protected $signature = 'openapi:check';

    protected $description = 'Detecta drift entre rotas api/v1 e o contrato OpenAPI (specs/…/openapi.yaml).';

    public function handle(): int
    {
        /** @var list<string> $openApiFiles */
        $openApiFiles = [
            base_path('specs/001-fundacao-multitenant/contracts/openapi.yaml'),
            base_path('specs/002-crm-pacientes/contracts/openapi.yaml'),
            base_path('specs/003-omnichannel-inbox/contracts/openapi.yaml'),
            base_path('specs/004-token-auth-migration/contracts/openapi.yaml'),
        ];

        foreach ($openApiFiles as $openApiFile) {
            if (! file_exists($openApiFile)) {
                $this->error("Arquivo OpenAPI não encontrado: {$openApiFile}");

                return self::FAILURE;
            }
        }

        /** @var list<string> $excludedPaths */
        $excludedPaths = config('openapi-check.excluded_paths', []);

        // Captura JSON de route:list sem escrever no terminal.
        Artisan::call('route:list', [
            '--json' => true,
            '--path' => 'api/v1',
            '--except-vendor' => true,
        ]);

        $routeJson = Artisan::output();

        $checker = new DriftChecker($excludedPaths);
        $result = $checker->checkMultiple($openApiFiles, $routeJson);

        $this->line('OpenAPI Drift Check');
        $this->line(str_repeat('─', 60));
        $this->line("Rotas reais (api/v1, excluindo whitelist): {$result['total_real']}");
        $this->line("Paths no OpenAPI (excluindo whitelist):    {$result['total_spec']}");
        $this->line(str_repeat('─', 60));

        if (empty($result['errors'])) {
            $this->info('[OK] Nenhum drift detectado. Contrato OpenAPI sincronizado.');

            return self::SUCCESS;
        }

        $this->error('[DRIFT] '.count($result['errors']).' problema(s) encontrado(s):');
        $this->newLine();

        foreach ($result['errors'] as $i => $error) {
            $this->line('  '.($i + 1).'. '.$error);
        }

        $this->newLine();

        return self::FAILURE;
    }
}
