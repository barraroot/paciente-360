<?php

namespace Tests\Unit\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Garante que a trait `BelongsToTenant` + scope `TenantScope`:
 *  - injeta `WHERE tenant_id = ?` em toda query;
 *  - popula `tenant_id` automaticamente em `creating`;
 *  - permite bypass via `withoutTenantScope()`.
 *
 * Usa a tabela `professionals` (já criada na migration T031) como Model
 * concreto, para evitar criar tabelas in-memory que divergiriam do
 * schema real.
 */
class BelongsToTenantScopeTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    public function test_query_includes_tenant_id_filter_when_tenant_is_bound(): void
    {
        $tenant = $this->createTenant();
        $this->app->instance('tenant', $tenant);

        $sql = TestProfessional::query()->toSql();

        $this->assertStringContainsString('"tenant_id"', $sql);
    }

    public function test_query_does_not_filter_when_no_tenant_is_bound(): void
    {
        $this->app->forgetInstance('tenant');

        $sql = TestProfessional::query()->toSql();

        $this->assertStringNotContainsString('"tenant_id" =', $sql);
    }

    public function test_creating_a_record_auto_populates_tenant_id(): void
    {
        $tenant = $this->createTenant();
        $this->app->instance('tenant', $tenant);

        $professional = TestProfessional::query()->create([
            'name' => 'Dr. Auto',
            'is_active' => true,
        ]);

        $this->assertSame($tenant->id, $professional->tenant_id);
    }

    public function test_explicit_tenant_id_is_preserved_on_creating(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $this->app->instance('tenant', $tenantA);

        $professional = TestProfessional::query()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Dr. Explicit',
            'is_active' => true,
        ]);

        $this->assertSame($tenantB->id, $professional->tenant_id);
    }

    public function test_without_tenant_scope_returns_records_from_all_tenants(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $this->app->instance('tenant', $tenantA);
        TestProfessional::query()->create(['name' => 'Dr A', 'is_active' => true]);

        $this->app->instance('tenant', $tenantB);
        TestProfessional::query()->create(['name' => 'Dr B', 'is_active' => true]);

        // Com tenant B bound, vê apenas o seu.
        $this->assertSame(1, TestProfessional::query()->count());

        // withoutTenantScope() vê todos.
        $this->assertSame(2, TestProfessional::withoutTenantScope()->count());
    }

    public function test_scope_is_registered_globally(): void
    {
        $scopes = (new TestProfessional)->getGlobalScopes();

        $this->assertArrayHasKey(TenantScope::class, $scopes);
    }

    public function test_tenant_model_does_not_use_belongs_to_tenant_trait(): void
    {
        // Sanity: a raiz do multi-tenancy nunca pode ser auto-filtrada
        // por si mesma.
        $scopes = (new Tenant)->getGlobalScopes();

        $this->assertArrayNotHasKey(TenantScope::class, $scopes);
    }
}

/**
 * Model interno de teste (extende Eloquent Model, não Authenticatable),
 * mapeado para a tabela `professionals` já existente.
 */
class TestProfessional extends Model
{
    use BelongsToTenant;

    protected $table = 'professionals';

    protected $guarded = [];

    public $timestamps = true;
}
