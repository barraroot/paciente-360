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
 * **T237 (Fase 8 — Lote D US-11.2)** — AC-11.2.6 — Endpoints fora do Q14 → 404.
 *
 * Não revela existência ao integrador.
 */
class PublicApiScopeRestrictionTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_unscoped_resource_returns_404(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('clinic-scope');
        $user = $this->userForRole($tenant, 'admin-clinica');
        Sanctum::actingAs($user, ['*']);

        // Recursos fora do Q14 — campaigns, reports, conversations, ai_decision_logs etc.
        $this->getJson('/api/public/v1/campaigns')->assertStatus(404);
        $this->getJson('/api/public/v1/reports')->assertStatus(404);
        $this->getJson('/api/public/v1/conversations')->assertStatus(404);
    }
}
