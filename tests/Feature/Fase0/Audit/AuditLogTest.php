<?php

namespace Tests\Feature\Fase0\Audit;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * T260 — Feature tests do GET /audit-logs (US-2.4 — FR-035..037).
 *
 * Cobre:
 *  - Autorização (admin-clinica, financeiro permitidos; médico negado).
 *  - Filtros por `action`, `actor_user_id`, range de data.
 *  - Paginação + cap de `per_page` em 200.
 *  - Isolamento entre tenants (Princípio II).
 *  - Shape da resposta conforme openapi.yaml § AuditLogResource.
 */
class AuditLogTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Cria tenant + roles do sistema + admin-clinica logado.
     *
     * @return array{tenant: Tenant, admin: User}
     */
    private function tenantWithAdmin(string $slug = 'clinica-audit'): array
    {
        $tenant = $this->createTenant(['slug' => $slug]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        foreach (['admin-clinica', 'financeiro', 'medico'] as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web', 'tenant_id' => $tenant->id]
            );
        }

        $admin = $this->createUserForTenant($tenant, ['status' => 'active']);
        $admin->assignRole('admin-clinica');

        return ['tenant' => $tenant, 'admin' => $admin];
    }

    private function url(string $slug, string $query = ''): string
    {
        $base = "http://{$slug}.lvh.me/api/v1/audit-logs";

        return $query === '' ? $base : "{$base}?{$query}";
    }

    /** @test */
    public function test_admin_clinica_can_list_audit_logs(): void
    {
        ['tenant' => $tenant, 'admin' => $admin] = $this->tenantWithAdmin('clinica-audit-list');

        AuditLog::factory()->count(5)->forTenant($tenant)->create();

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($admin)->getJson($this->url('clinica-audit-list'));

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    }

    /** @test */
    public function test_financeiro_can_list_audit_logs(): void
    {
        ['tenant' => $tenant] = $this->tenantWithAdmin('clinica-audit-fin');

        $financeiro = $this->createUserForTenant($tenant, ['status' => 'active']);
        $financeiro->assignRole('financeiro');

        AuditLog::factory()->count(2)->forTenant($tenant)->create();

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($financeiro)->getJson($this->url('clinica-audit-fin'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    /** @test */
    public function test_medico_cannot_list(): void
    {
        ['tenant' => $tenant] = $this->tenantWithAdmin('clinica-audit-med');

        $medico = $this->createUserForTenant($tenant, ['status' => 'active']);
        $medico->assignRole('medico');

        AuditLog::factory()->count(2)->forTenant($tenant)->create();

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($medico)->getJson($this->url('clinica-audit-med'));

        $response->assertForbidden();
    }

    /** @test */
    public function test_filter_by_action(): void
    {
        ['tenant' => $tenant, 'admin' => $admin] = $this->tenantWithAdmin('clinica-audit-action');

        AuditLog::factory()->count(3)->forTenant($tenant)->action('user.login.succeeded')->create();
        AuditLog::factory()->count(2)->forTenant($tenant)->action('tenant.registered')->create();

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($admin)
            ->getJson($this->url('clinica-audit-action', 'action=user.login.succeeded'));

        $response->assertOk();
        $response->assertJsonCount(3, 'data');

        foreach ($response->json('data') as $item) {
            $this->assertSame('user.login.succeeded', $item['action']);
        }
    }

    /** @test */
    public function test_filter_by_actor_user_id(): void
    {
        ['tenant' => $tenant, 'admin' => $admin] = $this->tenantWithAdmin('clinica-audit-actor');

        $u1 = $this->createUserForTenant($tenant);
        $u2 = $this->createUserForTenant($tenant);

        AuditLog::factory()->count(3)->forTenant($tenant)->byUser($u1)->create();
        AuditLog::factory()->count(2)->forTenant($tenant)->byUser($u2)->create();

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($admin)
            ->getJson($this->url('clinica-audit-actor', 'actor_user_id='.$u1->id));

        $response->assertOk();
        $response->assertJsonCount(3, 'data');

        foreach ($response->json('data') as $item) {
            $this->assertSame($u1->id, $item['actor']['user_id']);
        }
    }

    /** @test */
    public function test_filter_by_date_range(): void
    {
        ['tenant' => $tenant, 'admin' => $admin] = $this->tenantWithAdmin('clinica-audit-range');

        // Dentro do range
        AuditLog::factory()
            ->count(2)
            ->forTenant($tenant)
            ->state(['created_at' => '2026-01-15 10:00:00'])
            ->create();

        // Fora do range
        AuditLog::factory()
            ->count(3)
            ->forTenant($tenant)
            ->state(['created_at' => '2025-12-15 10:00:00'])
            ->create();

        AuditLog::factory()
            ->count(1)
            ->forTenant($tenant)
            ->state(['created_at' => '2026-02-15 10:00:00'])
            ->create();

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($admin)
            ->getJson($this->url('clinica-audit-range', 'from=2026-01-01&to=2026-01-31'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    /** @test */
    public function test_pagination_works(): void
    {
        ['tenant' => $tenant, 'admin' => $admin] = $this->tenantWithAdmin('clinica-audit-page');

        AuditLog::factory()->count(60)->forTenant($tenant)->create();

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($admin)
            ->getJson($this->url('clinica-audit-page', 'per_page=25&page=2'));

        $response->assertOk();
        $response->assertJsonCount(25, 'data');

        $meta = $response->json('meta');
        $this->assertSame(2, $meta['current_page']);
        $this->assertSame(25, $meta['per_page']);
        $this->assertSame(60, $meta['total']);
    }

    /** @test */
    public function test_per_page_max_200(): void
    {
        ['tenant' => $tenant, 'admin' => $admin] = $this->tenantWithAdmin('clinica-audit-cap');

        AuditLog::factory()->count(5)->forTenant($tenant)->create();

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($admin)
            ->getJson($this->url('clinica-audit-cap', 'per_page=500'));

        $response->assertOk();
        $meta = $response->json('meta');
        $this->assertSame(200, $meta['per_page']);
    }

    /** @test */
    public function test_tenant_isolation_enforced(): void
    {
        ['tenant' => $tenantA, 'admin' => $adminA] = $this->tenantWithAdmin('clinica-audit-iso-a');
        ['tenant' => $tenantB] = $this->tenantWithAdmin('clinica-audit-iso-b');

        AuditLog::factory()->count(2)->forTenant($tenantA)->action('only.in.a')->create();
        AuditLog::factory()->count(3)->forTenant($tenantB)->action('only.in.b')->create();

        $this->app->instance('tenant', $tenantA);

        $response = $this->actingAs($adminA)->getJson($this->url('clinica-audit-iso-a'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $item) {
            $this->assertSame('only.in.a', $item['action']);
        }
    }

    /** @test */
    public function test_response_shape_matches_openapi(): void
    {
        ['tenant' => $tenant, 'admin' => $admin] = $this->tenantWithAdmin('clinica-audit-shape');

        $actor = $this->createUserForTenant($tenant);

        AuditLog::factory()
            ->forTenant($tenant)
            ->byUser($actor)
            ->state([
                'action' => 'shape.test',
                'auditable_type' => 'App\Models\Tenant',
                'auditable_id' => $tenant->id,
                'payload' => ['k' => 'v'],
                'request_id' => '01J...REQ',
            ])
            ->create();

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($admin)->getJson($this->url('clinica-audit-shape'));

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'actor' => ['type', 'user_id', 'user_name', 'email'],
                    'action',
                    'auditable' => ['type', 'id'],
                    'payload',
                    'ip',
                    'user_agent',
                    'request_id',
                    'created_at',
                ],
            ],
            'meta' => ['current_page', 'per_page', 'total'],
        ]);

        $first = $response->json('data.0');
        $this->assertSame('user', $first['actor']['type']);
        $this->assertSame($actor->id, $first['actor']['user_id']);
        $this->assertSame($actor->name, $first['actor']['user_name']);
        $this->assertSame($actor->email, $first['actor']['email']);
        $this->assertSame('shape.test', $first['action']);
        $this->assertSame('App\Models\Tenant', $first['auditable']['type']);
    }
}
