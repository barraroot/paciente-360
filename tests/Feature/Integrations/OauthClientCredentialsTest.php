<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Domain\Integrations\Services\OauthClientService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T241 (Fase 8 — Lote D US-11.2)** — OAuth 2.0 Client Credentials (Q18).
 *
 * Skip se OAuth desabilitado (default false). Quando habilitado, emite
 * token JWT-like válido 1h.
 */
class OauthClientCredentialsTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_oauth_disabled_service_throws(): void
    {
        Config::set('finalization.oauth_enabled', false);

        $service = app(OauthClientService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('oauth_disabled');

        $tenant = $this->bootstrapTenantWithRoles('clinic-oauth-1');
        $user = $this->userForRole($tenant, 'admin-clinica');

        $service->createClient($tenant, $user, 'Test Client', ['patients.read']);
    }

    public function test_oauth_enabled_issues_access_token(): void
    {
        Config::set('finalization.oauth_enabled', true);

        $tenant = $this->bootstrapTenantWithRoles('clinic-oauth-2');
        $user = $this->userForRole($tenant, 'admin-clinica');

        $service = app(OauthClientService::class);

        $result = $service->createClient($tenant, $user, 'Zapier', ['patients.read', 'appointments.read']);

        $this->assertNotEmpty($result['client_secret']);
        $this->assertStringStartsWith('cs_', $result['client_secret']);

        $token = $service->issueAccessToken($result['client']->client_id, $result['client_secret']);

        $this->assertSame('Bearer', $token['token_type']);
        $this->assertSame(3600, $token['expires_in']);
        $this->assertStringStartsWith('stub.', $token['access_token']);
    }

    public function test_oauth_invalid_secret_throws(): void
    {
        Config::set('finalization.oauth_enabled', true);

        $tenant = $this->bootstrapTenantWithRoles('clinic-oauth-3');
        $user = $this->userForRole($tenant, 'admin-clinica');

        $service = app(OauthClientService::class);
        $result = $service->createClient($tenant, $user, 'X', ['patients.read']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid_client');

        $service->issueAccessToken($result['client']->client_id, 'wrong_secret');
    }
}
