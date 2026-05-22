<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Http\Middleware\ApiPublicRateLimiter;
use Database\Seeders\RolesSeeder;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T236 (Fase 8 — Lote D US-11.2)** — AC-11.2.4 — Rate limit por token.
 *
 * Validação unitária do middleware (testes E2E com 1100 requests reais
 * são deferred para CI dedicado).
 */
class PublicApiRateLimitTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_rate_limit_headers_present(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('clinic-rl');
        $user = $this->userForRole($tenant, 'admin-clinica');
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/public/v1/patients');

        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    public function test_middleware_returns_429_when_token_exceeds_limit(): void
    {
        // Acessa o limiter diretamente via middleware unit-style.
        $limiter = app(RateLimiter::class);

        // Simula 60 hits no token key.
        for ($i = 0; $i < 60; $i++) {
            $limiter->hit('api_public:token:anonymous', 60);
        }

        $middleware = app(ApiPublicRateLimiter::class);

        $response = $middleware->handle(
            Request::create('/api/public/v1/patients'),
            fn () => response()->json(['ok' => true])
        );

        $this->assertSame(429, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('token_rate_limit_exceeded', $body['error']);
    }
}
