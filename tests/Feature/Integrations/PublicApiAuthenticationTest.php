<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T235 (Fase 8 — Lote D US-11.2)** — AC-11.2.3 — Sanctum bearer auth.
 */
class PublicApiAuthenticationTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/public/v1/patients')
            ->assertStatus(401);
    }

    public function test_authenticated_request_succeeds(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('clinic-api-1');
        $user = $this->userForRole($tenant, 'admin-clinica');
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/public/v1/patients')
            ->assertOk();
    }
}
