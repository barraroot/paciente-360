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
 * **T240 (Fase 8 — Lote D US-11.2)** — 503 tenant_suspended.
 */
class PublicApiTenantSuspendedTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_suspended_tenant_returns_503(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('clinic-susp');
        $tenant->update([
            'suspended_at' => now(),
            'suspended_reason' => 'inadimplência',
        ]);

        $user = $this->userForRole($tenant, 'admin-clinica');
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/public/v1/patients')
            ->assertStatus(503)
            ->assertJson(['error' => 'tenant_suspended']);
    }
}
