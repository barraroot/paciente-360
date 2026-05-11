<?php

namespace App\Services\OpenApi;

use Symfony\Component\Yaml\Yaml;

/**
 * Compara rotas reais (route:list JSON) com o contrato OpenAPI (YAML).
 *
 * Utilizado tanto pelo `CheckOpenApiDrift` Artisan command quanto pelo
 * script standalone `scripts/check-openapi.php`.
 *
 * Regras de matching:
 *  - O prefixo `api/v1` é removido do URI do Laravel antes da comparação.
 *  - Nomes de path params são canonicalizados para `{}` (ex.: `{id}` = `{slug}`).
 *  - O verbo HTTP `HEAD` é ignorado (não é verbo OpenAPI).
 *  - Paths configurados em `excluded_paths` são ignorados nas duas direções.
 */
final class DriftChecker
{
    /**
     * @param list<string> $excludedPaths Caminhos (sem /api/v1) a ignorar
     */
    public function __construct(
        private readonly array $excludedPaths = [],
    ) {}

    /**
     * Executa a comparação contra múltiplos arquivos OpenAPI (paths são mergeados).
     *
     * @param list<string> $openApiFiles Caminhos absolutos para arquivos .yaml
     * @param string $routeJson Saída JSON de `php artisan route:list`
     * @return array{errors: list<string>, total_real: int, total_spec: int}
     */
    public function checkMultiple(array $openApiFiles, string $routeJson): array
    {
        $openApiRoutes = [];

        foreach ($openApiFiles as $openApiFile) {
            foreach ($this->parseOpenApiPaths($openApiFile) as $canonical => $methods) {
                foreach ($methods as $method => $val) {
                    $openApiRoutes[$canonical][$method] = $val;
                }
            }
        }

        $realRoutes = $this->parseRouteList($routeJson);

        return $this->compare($openApiRoutes, $realRoutes);
    }

    /**
     * Executa a comparação contra um único arquivo OpenAPI.
     *
     * @param string $openApiFile Caminho absoluto para o arquivo .yaml
     * @param string $routeJson Saída JSON de `php artisan route:list`
     * @return array{errors: list<string>, total_real: int, total_spec: int}
     */
    public function check(string $openApiFile, string $routeJson): array
    {
        $openApiRoutes = $this->parseOpenApiPaths($openApiFile);
        $realRoutes = $this->parseRouteList($routeJson);

        return $this->compare($openApiRoutes, $realRoutes);
    }

    /**
     * Compara rotas reais com paths OpenAPI já parseados.
     *
     * @param array<string, array<string, true>> $openApiRoutes
     * @param array<string, array<string, true>> $realRoutes
     * @return array{errors: list<string>, total_real: int, total_spec: int}
     */
    private function compare(array $openApiRoutes, array $realRoutes): array
    {

        $errors = [];

        // Rotas reais sem documentação no OpenAPI.
        foreach ($realRoutes as $path => $methods) {
            $canonical = $this->canonicalize($path);

            if (! isset($openApiRoutes[$canonical])) {
                $verbs = strtoupper(implode(', ', array_keys($methods)));
                $errors[] = "[UNDOCUMENTED] {$verbs} {$path} — rota em código mas ausente no OpenAPI.";

                continue;
            }

            foreach ($methods as $verb => $_) {
                if (! isset($openApiRoutes[$canonical][$verb])) {
                    $errors[] = '[VERB MISSING] '.strtoupper($verb)." {$path} — verbo em código mas ausente no OpenAPI.";
                }
            }
        }

        // Endpoints OpenAPI sem rota correspondente em código.
        foreach ($openApiRoutes as $canonical => $methods) {
            $matched = false;
            foreach ($realRoutes as $realPath => $_) {
                if ($this->canonicalize($realPath) === $canonical) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                $verbs = strtoupper(implode(', ', array_keys($methods)));
                $errors[] = "[ORPHAN] {$verbs} {$canonical} — no OpenAPI mas sem rota em código.";

                continue;
            }

            foreach ($methods as $verb => $_) {
                $verbFound = false;
                foreach ($realRoutes as $realPath => $realMethods) {
                    if ($this->canonicalize($realPath) === $canonical && isset($realMethods[$verb])) {
                        $verbFound = true;
                        break;
                    }
                }

                if (! $verbFound) {
                    $errors[] = '[ORPHAN VERB] '.strtoupper($verb)." {$canonical} — verbo no OpenAPI mas sem rota em código.";
                }
            }
        }

        return [
            'errors' => $errors,
            'total_real' => count($realRoutes),
            'total_spec' => count($openApiRoutes),
        ];
    }

    /**
     * Lê e parseia o OpenAPI YAML, retornando paths normalizados.
     *
     * @return array<string, array<string, true>> canonical_path => [method => true]
     */
    private function parseOpenApiPaths(string $file): array
    {
        /** @var array<string, mixed> $openApi */
        $openApi = Yaml::parseFile($file);

        /** @var array<string, array<string, mixed>> $paths */
        $paths = $openApi['paths'] ?? [];
        $result = [];

        foreach ($paths as $path => $methods) {
            if ($this->isExcluded($path)) {
                continue;
            }

            $canonical = $this->canonicalize($path);

            foreach ($methods as $method => $spec) {
                if (! is_array($spec) || $method === 'parameters') {
                    continue;
                }
                $result[$canonical][strtolower($method)] = true;
            }
        }

        return $result;
    }

    /**
     * Parseia JSON do `route:list`, retornando paths normalizados.
     *
     * @param string $json Saída JSON de `php artisan route:list`
     * @return array<string, array<string, true>> path => [method => true]
     */
    private function parseRouteList(string $json): array
    {
        /** @var list<array<string, mixed>> $routes */
        $routes = json_decode($json, true) ?? [];

        $result = [];

        foreach ($routes as $route) {
            $uri = (string) ($route['uri'] ?? '');

            if (! str_starts_with($uri, 'api/v1')) {
                continue;
            }

            $path = $this->normalizeUri($uri);

            if ($this->isExcluded($path)) {
                continue;
            }

            $rawMethod = strtoupper((string) ($route['method'] ?? 'GET'));
            $methods = explode('|', $rawMethod);

            foreach ($methods as $method) {
                $method = trim($method);
                if ($method === 'HEAD') {
                    continue;
                }
                $result[$path][strtolower($method)] = true;
            }
        }

        return $result;
    }

    /**
     * Normaliza URI do Laravel para path OpenAPI.
     * "api/v1/users/{id}" → "/users/{id}"
     */
    private function normalizeUri(string $uri): string
    {
        $path = (string) preg_replace('#^api/v1/?#', '', $uri);

        return '/'.ltrim($path, '/');
    }

    /**
     * Canonicaliza path substituindo nomes de parâmetros por `{}`.
     * "/users/{id}" → "/users/{}"
     */
    private function canonicalize(string $path): string
    {
        return (string) preg_replace('#\{[^}]+\}#', '{}', $path);
    }

    /**
     * Verifica se o path está na lista de exclusões.
     */
    private function isExcluded(string $path): bool
    {
        $clean = ltrim($path, '/');

        foreach ($this->excludedPaths as $pattern) {
            if ($clean === $pattern || str_starts_with($clean, rtrim($pattern, '/').'/')) {
                return true;
            }
        }

        return false;
    }
}
