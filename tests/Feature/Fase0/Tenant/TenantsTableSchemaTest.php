<?php

namespace Tests\Feature\Fase0\Tenant;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Verificações funcionais da migration `tenants` (T020).
 *
 * Usa o connection default do test (CI = pgsql; local Sail = pgsql) para
 * validar:
 *  - schema criado, com colunas canônicas presentes;
 *  - UNIQUE em `slug` (rejeita duplicado);
 *  - CHECK constraint em `status` (rejeita valor fora do enum).
 *
 * Princípio II — fundação multi-tenant; T020 é o gate de entrada da
 * fase.
 */
class TenantsTableSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenants_table_is_created(): void
    {
        $this->assertTrue(Schema::hasTable('tenants'));
    }

    public function test_tenants_has_all_canonical_columns(): void
    {
        $expected = [
            'id',
            'slug',
            'name',
            'cnpj',
            'responsible_name',
            'responsible_email',
            'responsible_phone',
            'status',
            'trial_ends_at',
            'overdue_since',
            'restrictions_applied_at',
            'plan_id',
            'stripe_customer_id',
            'subdomain_custom',
            'terms_accepted_at',
            'terms_version',
            'onboarding_state',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        foreach ($expected as $column) {
            $this->assertTrue(
                Schema::hasColumn('tenants', $column),
                "Coluna '{$column}' deve existir em tenants."
            );
        }
    }

    public function test_duplicate_slug_is_rejected(): void
    {
        $payload = $this->validTenantPayload(['slug' => 'clinica-alpha', 'cnpj' => '11111111000101']);
        DB::table('tenants')->insert($payload);

        $this->expectException(QueryException::class);

        DB::table('tenants')->insert($this->validTenantPayload([
            'slug' => 'clinica-alpha',
            'cnpj' => '22222222000102',
        ]));
    }

    public function test_duplicate_cnpj_is_rejected(): void
    {
        $payload = $this->validTenantPayload(['slug' => 'clinica-beta', 'cnpj' => '33333333000103']);
        DB::table('tenants')->insert($payload);

        $this->expectException(QueryException::class);

        DB::table('tenants')->insert($this->validTenantPayload([
            'slug' => 'clinica-gamma',
            'cnpj' => '33333333000103',
        ]));
    }

    public function test_status_check_constraint_rejects_unknown_value(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('CHECK constraint validada apenas em PostgreSQL (driver default do projeto).');
        }

        $this->expectException(QueryException::class);

        DB::table('tenants')->insert($this->validTenantPayload([
            'slug' => 'clinica-delta',
            'cnpj' => '44444444000104',
            'status' => 'frozen',
        ]));
    }

    public function test_status_default_is_trial_when_omitted(): void
    {
        DB::table('tenants')->insert($this->validTenantPayload([
            'slug' => 'clinica-epsilon',
            'cnpj' => '55555555000105',
        ]));

        $row = DB::table('tenants')->where('slug', 'clinica-epsilon')->first();

        $this->assertSame('trial', $row->status);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validTenantPayload(array $overrides = []): array
    {
        $now = now();

        return array_merge([
            'slug' => 'clinica-padrao',
            'name' => 'Clínica Padrão',
            'cnpj' => '99999999000199',
            'responsible_name' => 'Responsável Teste',
            'responsible_email' => 'responsavel@teste.local',
            'responsible_phone' => '+5511999998888',
            'terms_accepted_at' => $now,
            'terms_version' => '2026-05-01',
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides);
    }
}
