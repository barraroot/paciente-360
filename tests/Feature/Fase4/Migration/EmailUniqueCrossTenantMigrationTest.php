<?php

namespace Tests\Feature\Fase4\Migration;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * T013 — Testes da migration add_unique_email_global_constraint.
 *
 * Cobre: aplicação limpa, aborto com duplicatas e rollback.
 *
 * NOTA: RefreshDatabase já aplica todas as migrations, incluindo a nova.
 * Cada teste que precisa re-aplicar a migration faz rollback primeiro via
 * Artisan::call('migrate:rollback', ['--path' => ...]) para isolar o cenário.
 */
class EmailUniqueCrossTenantMigrationTest extends TestCase
{
    use RefreshDatabase;

    private string $migrationPath = 'database/migrations/2026_05_13_000001_add_unique_email_global_constraint.php';

    /** @test */
    public function test_migration_applies_clean_when_no_duplicates(): void
    {
        // Rollback para estado sem a migration, depois reaplicar
        Artisan::call('migrate:rollback', [
            '--path' => $this->migrationPath,
            '--realpath' => false,
        ]);

        // Assert: compound unique restored by rollback
        $compoundExists = DB::select(
            "SELECT 1 FROM pg_indexes WHERE tablename='users' AND indexname='users_email_tenant_unique'"
        );
        $this->assertNotEmpty($compoundExists, 'Compound unique should exist before migration re-apply');

        // Apply migration with no duplicates (default factory data has distinct emails)
        Artisan::call('migrate', [
            '--path' => $this->migrationPath,
            '--realpath' => false,
        ]);

        // Assert new global unique index exists
        $globalUniqueExists = DB::select(
            "SELECT 1 FROM pg_indexes WHERE tablename='users' AND indexname='users_email_unique'"
        );
        $this->assertNotEmpty($globalUniqueExists, 'Global unique index users_email_unique should exist after migration');

        // Assert old compound unique index is removed
        $compoundStillExists = DB::select(
            "SELECT 1 FROM pg_indexes WHERE tablename='users' AND indexname='users_email_tenant_unique'"
        );
        $this->assertEmpty($compoundStillExists, 'Old compound unique index should be removed after migration');
    }

    /** @test */
    public function test_migration_aborts_with_clear_error_when_duplicates_present(): void
    {
        // Rollback migration to get compound unique back
        Artisan::call('migrate:rollback', [
            '--path' => $this->migrationPath,
            '--realpath' => false,
        ]);

        // Create real tenants to satisfy FK constraint
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        // Drop compound index to insert duplicate
        DB::statement('DROP INDEX IF EXISTS users_email_tenant_unique');

        DB::table('users')->insert([
            'tenant_id' => $tenantA->id,
            'name' => 'User Alpha',
            'email' => 'dupe@migration.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'failed_login_attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'tenant_id' => $tenantB->id,
            'name' => 'User Beta',
            'email' => 'dupe@migration.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'failed_login_attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Restore compound index so migration can proceed to duplicate check
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS users_email_tenant_unique ON users (email, COALESCE(tenant_id, 0))');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/duplicad[oa]s/i');

        Artisan::call('migrate', [
            '--path' => $this->migrationPath,
            '--realpath' => false,
        ]);
    }

    /** @test */
    public function test_migration_rollback_restores_compound_unique(): void
    {
        // Migration is already applied by RefreshDatabase
        // Verify it's in applied state
        $globalExists = DB::select(
            "SELECT 1 FROM pg_indexes WHERE tablename='users' AND indexname='users_email_unique'"
        );
        $this->assertNotEmpty($globalExists, 'Migration should already be applied');

        // Rollback
        Artisan::call('migrate:rollback', [
            '--path' => $this->migrationPath,
            '--realpath' => false,
        ]);

        // Assert compound index restored
        $compoundRestored = DB::select(
            "SELECT 1 FROM pg_indexes WHERE tablename='users' AND indexname='users_email_tenant_unique'"
        );
        $this->assertNotEmpty($compoundRestored, 'Compound unique index should be restored after rollback');

        // Assert global unique removed
        $globalStillExists = DB::select(
            "SELECT 1 FROM pg_indexes WHERE tablename='users' AND indexname='users_email_unique'"
        );
        $this->assertEmpty($globalStillExists, 'Global unique index should be removed after rollback');

        // Re-apply for proper cleanup (other tests may depend on this state)
        Artisan::call('migrate', [
            '--path' => $this->migrationPath,
            '--realpath' => false,
        ]);
    }
}
