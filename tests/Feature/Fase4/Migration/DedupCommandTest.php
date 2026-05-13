<?php

namespace Tests\Feature\Fase4\Migration;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * T012 — Testes do comando users:dedupe-emails-cross-tenant.
 *
 * Cobre: check mode, auto mode, edge cases (null last_login, oldest user),
 * idempotência e bloqueio em produção.
 *
 * NOTA: RefreshDatabase aplica todas as migrations, incluindo a nova
 * users_email_unique (global). Para simular duplicatas, cada teste
 * que precisa inseri-las dropa temporariamente esse índice e o restaura.
 */
class DedupCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Insere dois usuários com o mesmo email em tenants distintos,
     * contornando temporariamente a constraint de unicidade global.
     *
     * No estado pós-migration T014, a constraint é users_email_unique (UNIQUE em email).
     * Em PG não se pode DROP INDEX diretamente quando há constraint — precisa
     * usar ALTER TABLE DROP CONSTRAINT.
     */
    private function insertDuplicateUsers(
        Tenant $tenantA,
        Tenant $tenantB,
        string $email,
        ?string $lastLoginA = null,
        ?string $lastLoginB = null
    ): array {
        // Drop constraint via ALTER TABLE (PG não permite DROP INDEX com constraint ativa)
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_unique');
        // Também limpa índice composto antigo caso ainda exista
        DB::statement('DROP INDEX IF EXISTS users_email_tenant_unique');

        $userA = User::factory()->forTenant($tenantA)->create([
            'email' => $email,
            'last_login_at' => $lastLoginA,
        ]);

        $userB = User::factory()->forTenant($tenantB)->create([
            'email' => $email,
            'last_login_at' => $lastLoginB,
        ]);

        return [$userA, $userB];
    }

    /**
     * Restaura o índice único global (estado pós-migration T014).
     */
    private function restoreUniqueIndex(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_unique');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_unique UNIQUE (email)');
    }

    /** @test */
    public function test_check_mode_returns_exit_0_when_no_duplicates(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'clinica-alfa-dedup1']);
        $tenantB = Tenant::factory()->create(['slug' => 'clinica-beta-dedup1']);

        User::factory()->forTenant($tenantA)->create(['email' => 'admin@a.test']);
        User::factory()->forTenant($tenantB)->create(['email' => 'admin@b.test']);

        $this->artisan('users:dedupe-emails-cross-tenant', ['--check' => true])
            ->assertExitCode(0);
    }

    /** @test */
    public function test_check_mode_returns_exit_1_when_duplicates_present(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'clinica-alfa-dedup2']);
        $tenantB = Tenant::factory()->create(['slug' => 'clinica-beta-dedup2']);

        // Insert duplicates — constraint is dropped inside insertDuplicateUsers
        $this->insertDuplicateUsers($tenantA, $tenantB, 'maria@example.com');

        // --check only reads data, does not write — can run without constraint active
        $this->artisan('users:dedupe-emails-cross-tenant', ['--check' => true])
            ->expectsOutputToContain('maria@example.com')
            ->assertExitCode(1);

        // Cleanup: remove duplicates before restoring constraint
        DB::table('users')->where('email', 'maria@example.com')->where('tenant_id', $tenantB->id)->delete();
        $this->restoreUniqueIndex();
    }

    /** @test */
    public function test_auto_mode_resolves_duplicates_keeping_most_recent_login_tenant(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'clinica-alfa-dedup3']);
        $tenantB = Tenant::factory()->create(['slug' => 'clinica-beta-dedup3']);

        [$userA, $userB] = $this->insertDuplicateUsers(
            $tenantA,
            $tenantB,
            'maria@example.com',
            '2026-05-01 10:00:00',  // tenant A mais recente
            '2026-04-22 10:00:00',  // tenant B mais antigo
        );

        // Mantém constraint dropada para --auto poder escrever renomeações
        $this->artisan('users:dedupe-emails-cross-tenant', ['--auto' => true])
            ->assertExitCode(0);

        $this->restoreUniqueIndex();

        $userA->refresh();
        $userB->refresh();

        // Tenant A (most recent login) keeps original email
        $this->assertEquals('maria@example.com', $userA->email);

        // Tenant B gets renamed with suffix containing tenant slug
        $this->assertStringStartsWith('maria@example.com', $userB->email);
        $this->assertStringContainsString('clinica-beta-dedup3', $userB->email);
    }

    /** @test */
    public function test_auto_mode_resolves_duplicates_keeping_most_recent_login_and_writes_audit_log(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'clinica-alfa-dedup4']);
        $tenantB = Tenant::factory()->create(['slug' => 'clinica-beta-dedup4']);

        [$userA, $userB] = $this->insertDuplicateUsers(
            $tenantA,
            $tenantB,
            'audit@example.com',
            '2026-05-01 10:00:00',
            '2026-04-22 10:00:00',
        );

        Log::shouldReceive('info')
            ->with('user.email_renamed_for_global_uniqueness', \Mockery::on(function (array $context) use ($userB): bool {
                return $context['user_id'] === $userB->id
                    && $context['old_email'] === 'audit@example.com'
                    && str_starts_with($context['new_email'], 'audit@example.com');
            }))
            ->once();

        $this->artisan('users:dedupe-emails-cross-tenant', ['--auto' => true])
            ->assertExitCode(0);

        $this->restoreUniqueIndex();
    }

    /** @test */
    public function test_auto_mode_with_no_login_history_keeps_oldest_user(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'clinica-alfa-dedup5']);
        $tenantB = Tenant::factory()->create(['slug' => 'clinica-beta-dedup5']);

        // Both with null last_login_at — lower ID (created first) should keep email
        [$userA, $userB] = $this->insertDuplicateUsers(
            $tenantA,
            $tenantB,
            'null-login@example.com',
            null,
            null,
        );

        $this->artisan('users:dedupe-emails-cross-tenant', ['--auto' => true])
            ->assertExitCode(0);

        $this->restoreUniqueIndex();

        $userA->refresh();
        $userB->refresh();

        // User A has lower ID (created first) — keeps original email
        $this->assertEquals('null-login@example.com', $userA->email);
        $this->assertStringContainsString('clinica-beta-dedup5', $userB->email);
    }

    /** @test */
    public function test_command_is_idempotent_re_run_after_dedup_does_nothing(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'clinica-alfa-dedup6']);
        $tenantB = Tenant::factory()->create(['slug' => 'clinica-beta-dedup6']);

        $this->insertDuplicateUsers(
            $tenantA,
            $tenantB,
            'idempotent@example.com',
            '2026-05-01 10:00:00',
            '2026-04-22 10:00:00',
        );

        // First run — resolves duplicates
        $this->artisan('users:dedupe-emails-cross-tenant', ['--auto' => true])
            ->assertExitCode(0);

        $this->restoreUniqueIndex();

        // Second run — no duplicates, should exit 0
        $this->artisan('users:dedupe-emails-cross-tenant', ['--auto' => true])
            ->assertExitCode(0);
    }

    /** @test */
    public function test_command_blocked_in_production_environment(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $this->artisan('users:dedupe-emails-cross-tenant', ['--auto' => true])
            ->expectsOutputToContain('Bloqueado')
            ->assertExitCode(1);

        // Restore testing environment
        app()->detectEnvironment(fn (): string => 'testing');
    }

    /** @test */
    public function test_check_mode_with_no_option_returns_exit_1_with_error(): void
    {
        $this->artisan('users:dedupe-emails-cross-tenant')
            ->expectsOutputToContain('Especifique modo')
            ->assertExitCode(1);
    }
}
