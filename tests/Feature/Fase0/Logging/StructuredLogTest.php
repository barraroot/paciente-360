<?php

namespace Tests\Feature\Fase0\Logging;

use App\Http\Middleware\LogStructuredRequestData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Testes do middleware `LogStructuredRequestData` (T052 — Princípio V).
 *
 * Estratégia:
 *  - Instancia o middleware diretamente e executa o `handle()` com
 *    requests sintéticos para verificar o contexto compartilhado.
 *  - Para o teste de headers HTTP, usa o HTTP test client normal.
 *  - O `Log::spy()` permite interceptar chamadas sem escrever em disco.
 *
 * @see App\Http\Middleware\LogStructuredRequestData
 * @see specs/001-fundacao-multitenant/spec.md — RNF-016 (observabilidade)
 */
class StructuredLogTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    /**
     * Request autenticada deve popular request_id, tenant_id e user_id
     * no contexto compartilhado de log.
     *
     * Usa o middleware diretamente e lê o `sharedContext` do LogManager
     * via reflexão (a propriedade não tem getter público no Laravel 13).
     */
    public function test_authenticated_request_includes_three_fields_in_logs(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-log']);
        $user = $this->createUserForTenant($tenant);

        // Injeta tenant no container (simula o ResolveTenant).
        $this->app->instance('tenant', $tenant);
        Sanctum::actingAs($user, ['*']);

        $middleware = new LogStructuredRequestData;
        $request = Request::create('/api/v1/_ping', 'GET');
        $request->setLaravelSession($this->app['session']->driver());

        $middleware->handle($request, fn (): Response => new Response('ok', 200));

        $capturedContext = $this->getSharedLogContext();

        $this->assertArrayHasKey('request_id', $capturedContext);
        $this->assertArrayHasKey('tenant_id', $capturedContext);
        $this->assertArrayHasKey('user_id', $capturedContext);
        $this->assertNotEmpty($capturedContext['request_id']);
        $this->assertSame($tenant->id, $capturedContext['tenant_id']);
        $this->assertSame($user->id, $capturedContext['user_id']);
    }

    /**
     * Request anônima deve ter user_id null e request_id gerado.
     */
    public function test_unauthenticated_request_logs_user_id_null(): void
    {
        $middleware = new LogStructuredRequestData;
        $request = Request::create('/api/v1/_ping', 'GET');

        $middleware->handle($request, fn (): Response => new Response('ok', 200));

        $capturedContext = $this->getSharedLogContext();

        $this->assertArrayHasKey('request_id', $capturedContext);
        $this->assertNotEmpty($capturedContext['request_id'], 'request_id deve ser gerado quando ausente no header.');
        $this->assertNull($capturedContext['user_id'], 'user_id deve ser null para request anônima.');
    }

    /**
     * Lê a propriedade `sharedContext` do LogManager via reflexão.
     * Necessário pois o Laravel 13 não expõe getter público para este campo.
     *
     * @return array<string, mixed>
     */
    private function getSharedLogContext(): array
    {
        $manager = Log::getFacadeRoot();
        $reflection = new \ReflectionClass($manager);
        $property = $reflection->getProperty('sharedContext');

        return (array) $property->getValue($manager);
    }

    /**
     * O header X-Request-Id da response deve ser idêntico ao da request
     * (ou ao gerado internamente quando ausente).
     */
    public function test_response_echoes_x_request_id(): void
    {
        $requestId = (string) Str::ulid();

        $tenant = $this->createTenant(['slug' => 'clinica-echo']);
        $user = $this->createUserForTenant($tenant);

        Sanctum::actingAs($user);

        $response = $this->withHeaders([
            'X-Request-Id' => $requestId,
            'Host' => 'clinica-echo.lvh.me',
        ])->getJson('/api/v1/_me');

        $response->assertOk();
        $this->assertSame(
            $requestId,
            $response->headers->get('X-Request-Id'),
            'O header X-Request-Id da response deve refletir o da request.'
        );
    }

    /**
     * Quando não há X-Request-Id na request, a response deve gerar e ecoar
     * um ID próprio.
     */
    public function test_response_generates_x_request_id_when_absent(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-gen']);
        $user = $this->createUserForTenant($tenant);

        Sanctum::actingAs($user);

        $response = $this->withHeaders([
            'Host' => 'clinica-gen.lvh.me',
        ])->getJson('/api/v1/_me');

        $response->assertOk();
        $this->assertNotEmpty(
            $response->headers->get('X-Request-Id'),
            'A response deve sempre conter X-Request-Id mesmo quando não enviado na request.'
        );
    }
}
