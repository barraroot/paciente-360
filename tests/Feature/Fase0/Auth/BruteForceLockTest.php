<?php

namespace Tests\Feature\Fase0\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Feature tests de brute-force lock por usuário (T101).
 *
 * Lock é por usuário (não por IP). O rate limiter `throttle:login` é
 * proteção adicional por IP+host e coexiste com este mecanismo.
 *
 * @see specs/001-fundacao-multitenant/tasks.md — T101
 */
class BruteForceLockTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    private function loginUrl(string $slug): string
    {
        return "http://{$slug}.lvh.me/api/v1/auth/login";
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Desabilitar throttle de IP para que o brute-force lock POR USUÁRIO
        // possa ser testado sem interferência do rate limiter por IP.
        // Os dois mecanismos coexistem em produção mas são testados separadamente
        // (rate limit por IP é testado em RateLimitTest).
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /** @test */
    public function test_account_locks_after_5_failed_attempts(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-lock', 'status' => 'active']);
        $user = $this->createUserForTenant($tenant, [
            'email' => 'victim@clinica-lock.com',
            'password' => Hash::make('real-pass'),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        // 5 tentativas com senha errada — cada uma retorna 401
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson(
                $this->loginUrl('clinica-lock'),
                ['email' => 'victim@clinica-lock.com', 'password' => 'wrong-pass']
            );
            $response->assertUnauthorized();
        }

        $user->refresh();
        $this->assertSame(5, (int) $user->failed_login_attempts);
        $this->assertNotNull($user->locked_until);

        // 6ª tentativa — conta bloqueada, retorna 423 mesmo com senha correta
        $response = $this->postJson(
            $this->loginUrl('clinica-lock'),
            ['email' => 'victim@clinica-lock.com', 'password' => 'real-pass']
        );

        $response->assertStatus(423);
        $response->assertJsonPath('error', 'account_locked');
        $response->assertJsonStructure(['error', 'locked_until']);
    }

    /** @test */
    public function test_lock_expires_naturally(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-expired', 'status' => 'active']);
        $user = $this->createUserForTenant($tenant, [
            'email' => 'expired@clinica-expired.com',
            'password' => Hash::make('correct-pass'),
            'failed_login_attempts' => 5,
            'locked_until' => now()->subMinute(), // lock já expirado
            'status' => 'active',
        ]);

        $response = $this->postJson(
            $this->loginUrl('clinica-expired'),
            ['email' => 'expired@clinica-expired.com', 'password' => 'correct-pass']
        );

        $response->assertOk();

        $user->refresh();
        $this->assertSame(0, (int) $user->failed_login_attempts);
        $this->assertNull($user->locked_until);
    }

    /** @test */
    public function test_successful_login_resets_failed_counter(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-reset', 'status' => 'active']);
        $user = $this->createUserForTenant($tenant, [
            'email' => 'partial@clinica-reset.com',
            'password' => Hash::make('correct-pass'),
            'failed_login_attempts' => 3,
            'locked_until' => null,
            'status' => 'active',
        ]);

        $response = $this->postJson(
            $this->loginUrl('clinica-reset'),
            ['email' => 'partial@clinica-reset.com', 'password' => 'correct-pass']
        );

        $response->assertOk();

        $user->refresh();
        $this->assertSame(0, (int) $user->failed_login_attempts);
    }

    /** @test */
    public function test_lock_audited(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-audit-lock', 'status' => 'active']);
        $user = $this->createUserForTenant($tenant, [
            'email' => 'audit@clinica-audit-lock.com',
            'password' => Hash::make('real-pass'),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        // 5 tentativas — a 5ª dispara o lock
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson(
                $this->loginUrl('clinica-audit-lock'),
                ['email' => 'audit@clinica-audit-lock.com', 'password' => 'wrong']
            );
        }

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.login.locked',
            'user_id' => $user->id,
        ]);
    }
}
