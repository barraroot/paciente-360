<?php

namespace Tests\Unit\Console;

use App\Services\OpenApi\DriftChecker;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários do `DriftChecker` — lógica de comparação OpenAPI vs rotas.
 *
 * Usa fixtures YAML em tests/Unit/Console/fixtures/openapi/ e JSON inline
 * simulando a saída de `php artisan route:list`.
 */
class CheckOpenApiDriftTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureDir = __DIR__.'/fixtures/openapi';
    }

    /**
     * Monta JSON de route:list simulando as rotas base de teste.
     *
     * @param list<array{uri: string, method: string}> $extra Rotas adicionais
     */
    private function makeRouteJson(array $extra = []): string
    {
        $base = [
            ['uri' => 'api/v1/users', 'method' => 'GET|HEAD'],
            ['uri' => 'api/v1/users/{id}', 'method' => 'PATCH'],
            ['uri' => 'api/v1/users/{id}', 'method' => 'DELETE'],
            ['uri' => 'api/v1/auth/login', 'method' => 'POST'],
        ];

        return json_encode(array_merge($base, $extra), JSON_THROW_ON_ERROR);
    }

    /**
     * T300-1: sem drift — contrato completo e rotas correspondem.
     */
    public function test_passes_when_routes_match_openapi(): void
    {
        $checker = new DriftChecker([]);
        $result = $checker->check(
            $this->fixtureDir.'/complete.yaml',
            $this->makeRouteJson(),
        );

        $this->assertEmpty($result['errors'], implode("\n", $result['errors']));
        $this->assertSame(0, count($result['errors']));
    }

    /**
     * T300-2: rota em código não documentada no OpenAPI → erro UNDOCUMENTED.
     */
    public function test_fails_when_route_missing_in_openapi(): void
    {
        // Fixture "missing_route.yaml" não tem /users/{id}
        $checker = new DriftChecker([]);
        $result = $checker->check(
            $this->fixtureDir.'/missing_route.yaml',
            $this->makeRouteJson(),
        );

        $this->assertNotEmpty($result['errors']);

        $errorTexts = implode(' ', $result['errors']);
        $this->assertStringContainsString('UNDOCUMENTED', $errorTexts);
        $this->assertStringContainsString('/users/', $errorTexts);
    }

    /**
     * T300-3: endpoint no OpenAPI sem rota correspondente → erro ORPHAN.
     */
    public function test_fails_when_openapi_has_orphan_path(): void
    {
        // Fixture "orphan.yaml" tem /reports que não existe nas rotas.
        $checker = new DriftChecker([]);
        $result = $checker->check(
            $this->fixtureDir.'/orphan.yaml',
            $this->makeRouteJson(),
        );

        $this->assertNotEmpty($result['errors']);

        $errorTexts = implode(' ', $result['errors']);
        $this->assertStringContainsString('ORPHAN', $errorTexts);
        $this->assertStringContainsString('/reports', $errorTexts);
    }

    /**
     * T300-4: paths em excluded_paths são ignorados nas duas direções.
     */
    public function test_excludes_paths_in_config(): void
    {
        // Adiciona rotas dummy que não estão no contrato "complete.yaml".
        $routeJson = $this->makeRouteJson([
            ['uri' => 'api/v1/_ping', 'method' => 'GET|HEAD'],
            ['uri' => 'api/v1/_me', 'method' => 'GET|HEAD'],
        ]);

        // Sem exclusão → drift detectado.
        $checker = new DriftChecker([]);
        $result = $checker->check($this->fixtureDir.'/complete.yaml', $routeJson);
        $this->assertNotEmpty($result['errors']);

        // Com exclusão → sem drift.
        $checkerWithExclusion = new DriftChecker(['_ping', '_me']);
        $resultClean = $checkerWithExclusion->check(
            $this->fixtureDir.'/complete.yaml',
            $routeJson,
        );

        $this->assertEmpty($resultClean['errors'], implode("\n", $resultClean['errors']));
    }
}
