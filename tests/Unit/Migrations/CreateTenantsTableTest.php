<?php

namespace Tests\Unit\Migrations;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Smoke test estrutural da migration `tenants` (T020).
 *
 * O gate funcional (subir o schema em PG18 e fazer `\d tenants`) roda no
 * CI via `phpunit-coverage`. Aqui garantimos que **o arquivo da
 * migration não pode ser editado removendo colunas/índices canônicos**
 * sem quebrar este teste — barreira contra regressões silenciosas no
 * data-model § 1.
 */
class CreateTenantsTableTest extends TestCase
{
    private string $migrationPath;

    private string $migrationSource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrationPath = database_path('migrations/2026_05_10_000001_create_tenants_table.php');
        $this->migrationSource = file_get_contents($this->migrationPath);
    }

    public function test_migration_file_exists(): void
    {
        $this->assertFileExists($this->migrationPath);
    }

    public function test_creates_tenants_table(): void
    {
        $this->assertMatchesRegularExpression(
            "/Schema::create\(\s*['\"]tenants['\"]/",
            $this->migrationSource
        );
    }

    #[DataProvider('canonicalColumns')]
    public function test_declares_canonical_column(string $column): void
    {
        $this->assertMatchesRegularExpression(
            "/['\"]".preg_quote($column, '/')."['\"]/",
            $this->migrationSource,
            "Coluna '{$column}' do data-model § 1 deve estar na migration."
        );
    }

    public static function canonicalColumns(): array
    {
        return [
            ['slug'],
            ['name'],
            ['cnpj'],
            ['responsible_name'],
            ['responsible_email'],
            ['responsible_phone'],
            ['status'],
            ['trial_ends_at'],
            ['overdue_since'],
            ['restrictions_applied_at'],
            ['plan_id'],
            ['stripe_customer_id'],
            ['subdomain_custom'],
            ['terms_accepted_at'],
            ['terms_version'],
            ['onboarding_state'],
        ];
    }

    public function test_uses_timestamptz_for_temporal_columns(): void
    {
        // Convenção do data-model: TIMESTAMPTZ, não TIMESTAMP.
        foreach (['trial_ends_at', 'overdue_since', 'restrictions_applied_at', 'terms_accepted_at'] as $column) {
            $this->assertMatchesRegularExpression(
                "/timestampTz\(\s*['\"]".$column."['\"]/",
                $this->migrationSource,
                "Coluna '{$column}' deve ser declarada via timestampTz()."
            );
        }

        $this->assertStringContainsString('timestampsTz()', $this->migrationSource);
        $this->assertStringContainsString('softDeletesTz()', $this->migrationSource);
    }

    public function test_onboarding_state_uses_jsonb(): void
    {
        $this->assertMatchesRegularExpression(
            "/jsonb\(\s*['\"]onboarding_state['\"]/",
            $this->migrationSource,
            'onboarding_state deve ser JSONB para suportar operadores de índice nativos do PG.'
        );
    }

    public function test_slug_and_cnpj_are_unique(): void
    {
        $this->assertMatchesRegularExpression(
            "/string\(\s*['\"]slug['\"][^)]*\)\s*->\s*unique\(\)/s",
            $this->migrationSource,
            'slug deve ter constraint UNIQUE.'
        );

        $this->assertMatchesRegularExpression(
            "/string\(\s*['\"]cnpj['\"][^)]*\)\s*->\s*unique\(\)/s",
            $this->migrationSource,
            'cnpj deve ter constraint UNIQUE.'
        );
    }

    public function test_status_and_overdue_since_have_index(): void
    {
        $this->assertMatchesRegularExpression(
            "/string\(\s*['\"]status['\"][^)]*\)[^;]*->\s*index\(\)/s",
            $this->migrationSource,
            'status deve ter INDEX para queries de gate (overdue/suspended).'
        );

        $this->assertMatchesRegularExpression(
            "/timestampTz\(\s*['\"]overdue_since['\"][^)]*\)[^;]*->\s*index\(\)/s",
            $this->migrationSource,
            'overdue_since deve ter INDEX (job D+7).'
        );
    }

    public function test_status_check_constraint_emulates_enum(): void
    {
        // CHECK constraint protege contra escritas via raw SQL com valores
        // fora do enum canônico (Princípio I — auditabilidade).
        $this->assertStringContainsString('tenants_status_check', $this->migrationSource);

        foreach (['trial', 'active', 'overdue', 'suspended', 'cancelled'] as $value) {
            $this->assertStringContainsString(
                "'{$value}'",
                $this->migrationSource,
                "Status '{$value}' deve constar na CHECK constraint."
            );
        }
    }

    public function test_status_default_is_trial(): void
    {
        $this->assertMatchesRegularExpression(
            "/string\(\s*['\"]status['\"][^)]*\)\s*->\s*default\(\s*['\"]trial['\"]/s",
            $this->migrationSource,
            "Default de status deve ser 'trial' (novo tenant entra em trial)."
        );
    }
}
