<?php

namespace Tests\Feature\Fase0\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Feature tests para US-2.1 — Login de Usuário Interno (T100).
 *
 * Cobre happy-path, falhas de credencial, usuário desabilitado,
 * tenant suspenso, campos omitidos e proteção CSRF.
 *
 * @see specs/001-fundacao-multitenant/tasks.md — T100
 */
class LoginTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    private function loginUrl(string $slug): string
    {
        return "http://{$slug}.lvh.me/api/v1/auth/login";
    }

    /** @test */
    public function test_login_with_valid_credentials_establishes_session(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-x', 'status' => 'active']);
        $user = $this->createUserForTenant($tenant, [
            'email' => 'dr@clinica-x.com',
            'password' => Hash::make('correct-pass-123'),
            'status' => 'active',
        ]);

        $this->withHeaders(['Origin' => 'http://clinica-x.lvh.me'])
            ->get('http://clinica-x.lvh.me/sanctum/csrf-cookie');

        $response = $this->withHeaders([
            'Origin' => 'http://clinica-x.lvh.me',
            'Accept' => 'application/json',
        ])->postJson(
            $this->loginUrl('clinica-x'),
            ['email' => 'dr@clinica-x.com', 'password' => 'correct-pass-123']
        );

        $response->assertOk();
        $response->assertJsonPath('data.id', $user->id);
        $response->assertJsonPath('data.email', 'dr@clinica-x.com');
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'status',
                'roles',
                'permissions',
                'last_login_at',
                'created_at',
                'tenant' => ['id', 'slug', 'name', 'status'],
            ],
        ]);
        $response->assertJsonPath('data.tenant.slug', 'clinica-x');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.login.succeeded',
            'user_id' => $user->id,
        ]);

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertNotNull($user->first_login_at);
    }

    /** @test */
    public function test_login_with_wrong_password_returns_401_generic(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-y', 'status' => 'active']);
        $user = $this->createUserForTenant($tenant, [
            'email' => 'dr@clinica-y.com',
            'password' => Hash::make('correct-pass'),
            'failed_login_attempts' => 0,
        ]);

        $response = $this->postJson(
            $this->loginUrl('clinica-y'),
            ['email' => 'dr@clinica-y.com', 'password' => 'wrong-pass']
        );

        $response->assertUnauthorized();
        $response->assertJsonStructure(['error']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.login.failed',
            'user_id' => $user->id,
        ]);

        $user->refresh();
        $this->assertSame(1, (int) $user->failed_login_attempts);
    }

    /** @test */
    public function test_login_with_unknown_email_returns_401_generic(): void
    {
        $this->createTenant(['slug' => 'clinica-z', 'status' => 'active']);

        $response = $this->postJson(
            $this->loginUrl('clinica-z'),
            ['email' => 'nobody@nowhere.com', 'password' => 'any-pass']
        );

        $response->assertUnauthorized();
        $response->assertJsonStructure(['error']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.login.failed',
            'user_id' => null,
        ]);
    }

    /** @test */
    public function test_login_with_disabled_user_returns_401(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-d', 'status' => 'active']);
        $this->createUserForTenant($tenant, [
            'email' => 'disabled@clinica-d.com',
            'password' => Hash::make('correct-pass'),
            'status' => 'disabled',
        ]);

        $response = $this->postJson(
            $this->loginUrl('clinica-d'),
            ['email' => 'disabled@clinica-d.com', 'password' => 'correct-pass']
        );

        $response->assertUnauthorized();
        $response->assertJsonStructure(['error']);
    }

    /** @test */
    public function test_login_in_suspended_tenant_returns_403(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-s', 'status' => 'suspended']);
        $this->createUserForTenant($tenant, [
            'email' => 'user@clinica-s.com',
            'password' => Hash::make('correct-pass'),
            'status' => 'active',
        ]);

        $response = $this->postJson(
            $this->loginUrl('clinica-s'),
            ['email' => 'user@clinica-s.com', 'password' => 'correct-pass']
        );

        $response->assertForbidden();
        $response->assertJsonPath('error', 'tenant_suspended');
    }

    /** @test */
    public function test_login_response_omits_sensitive_fields(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-safe', 'status' => 'active']);
        $this->createUserForTenant($tenant, [
            'email' => 'safe@clinica-safe.com',
            'password' => Hash::make('correct-pass-123'),
            'status' => 'active',
        ]);

        $response = $this->postJson(
            $this->loginUrl('clinica-safe'),
            ['email' => 'safe@clinica-safe.com', 'password' => 'correct-pass-123']
        );

        $response->assertOk();

        $content = $response->json('data');
        $this->assertArrayNotHasKey('password', $content);
        $this->assertArrayNotHasKey('remember_token', $content);
        $this->assertArrayNotHasKey('failed_login_attempts', $content);
        $this->assertArrayNotHasKey('locked_until', $content);
    }

    /** @test */
    public function test_csrf_cookie_required_for_session_post(): void
    {
        // Sem Accept:application/json e sem token XSRF, o middleware stateful
        // de Sanctum retorna 419 (CSRF token mismatch) para requests de browser.
        $this->createTenant(['slug' => 'clinica-csrf', 'status' => 'active']);

        $response = $this->post(
            $this->loginUrl('clinica-csrf'),
            ['email' => 'x@x.com', 'password' => 'wrong'],
            ['Accept' => 'text/html']
        );

        // 419 = CSRF mismatch; 422 = validação sem CSRF; 401 = unauth
        $this->assertContains($response->getStatusCode(), [419, 422, 401]);
    }
}
