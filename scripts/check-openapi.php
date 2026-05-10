#!/usr/bin/env php
<?php

/**
 * Script de validação de drift entre rotas reais e o contrato OpenAPI.
 *
 * Uso standalone (CI sem Artisan):
 *   php scripts/check-openapi.php
 *
 * Via Artisan (preferido em local):
 *   php artisan openapi:check
 *
 * Exit codes:
 *   0 — sem drift detectado.
 *   1 — drift detectado (veja saída).
 *   2 — erro de configuração / arquivo não encontrado.
 */

declare(strict_types=1);

$root = dirname(__DIR__);

if (! file_exists("{$root}/vendor/autoload.php")) {
    fwrite(STDERR, "[ERRO] vendor/autoload.php não encontrado. Rode `composer install`.\n");
    exit(2);
}

require "{$root}/vendor/autoload.php";

use App\Services\OpenApi\DriftChecker;

// ---------------------------------------------------------------------------
// Configuração
// ---------------------------------------------------------------------------

$openApiFile = "{$root}/specs/001-fundacao-multitenant/contracts/openapi.yaml";
$configFile = "{$root}/config/openapi-check.php";

$excludedPaths = [];
if (file_exists($configFile)) {
    $config = require $configFile;
    $excludedPaths = $config['excluded_paths'] ?? [];
}

if (! file_exists($openApiFile)) {
    fwrite(STDERR, "[ERRO] OpenAPI não encontrado: {$openApiFile}\n");
    exit(2);
}

// ---------------------------------------------------------------------------
// Obter lista de rotas reais via env var (injetado pelo Command) ou artisan
// ---------------------------------------------------------------------------

$routeJson = getenv('OPENAPI_CHECK_ROUTE_JSON') ?: null;

if ($routeJson === null) {
    $phpBin = PHP_BINARY;
    $artisan = "{$root}/artisan";

    if (! file_exists($artisan)) {
        fwrite(STDERR, "[ERRO] artisan não encontrado em: {$artisan}\n");
        exit(2);
    }

    $command = "{$phpBin} {$artisan} route:list --json --path=api/v1 --except-vendor 2>/dev/null";
    $routeJson = (string) shell_exec($command);
}

if (empty($routeJson)) {
    fwrite(STDERR, "[ERRO] Nenhuma rota retornada por route:list.\n");
    exit(2);
}

// ---------------------------------------------------------------------------
// Comparar usando DriftChecker
// ---------------------------------------------------------------------------

try {
    $checker = new DriftChecker($excludedPaths);
    $result = $checker->check($openApiFile, $routeJson);
} catch (Throwable $e) {
    fwrite(STDERR, "[ERRO] {$e->getMessage()}\n");
    exit(2);
}

echo "OpenAPI Drift Check\n";
echo str_repeat('─', 60)."\n";
echo "Rotas reais (api/v1, excluindo whitelist): {$result['total_real']}\n";
echo "Paths no OpenAPI (excluindo whitelist):    {$result['total_spec']}\n";
echo str_repeat('─', 60)."\n";

if (empty($result['errors'])) {
    echo "[OK] Nenhum drift detectado. Contrato OpenAPI sincronizado.\n";
    exit(0);
}

echo '[DRIFT] '.count($result['errors'])." problema(s) encontrado(s):\n\n";

foreach ($result['errors'] as $i => $error) {
    echo '  '.($i + 1).'. '.$error."\n";
}

echo "\n";
exit(1);
