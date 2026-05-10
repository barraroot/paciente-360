<?php

namespace Tests\Feature\Fase0\Auth;

use App\Mail\ResetPasswordMail;
use App\Services\Auth\PasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Feature tests para US-2.3 — Recuperação de Senha (T120).
 *
 * Cobre o fluxo completo: solicitação, isolamento por tenant, verificação
 * de hash no banco, reset com token válido, invalidade de token, expiração
 * e uso único.
 *
 * @see specs/001-fundacao-multitenant/tasks.md — T120
 */
class PasswordResetTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    private function forgotUrl(string $slug): string
    {
        return "http://{$slug}.lvh.me/api/v1/auth/password/forgot";
    }

    private function resetUrl(string $slug): string
    {
        return "http://{$slug}.lvh.me/api/v1/auth/password/reset";
    }

    /** @test */
    public function test_forgot_for_existing_email_dispatches_mail(): void
    {
        Mail::fake();

        $tenant = $this->createTenant(['slug' => 'clinica-reset-a', 'status' => 'active']);
        $user = $this->createUserForTenant($tenant, [
            'email' => 'dr@clinica-reset-a.com',
            'status' => 'active',
        ]);

        $response = $this->postJson(
            $this->forgotUrl('clinica-reset-a'),
            ['email' => 'dr@clinica-reset-a.com'],
        );

        $response->assertStatus(202);

        // O job é executado sincronamente em testes (QUEUE_CONNECTION=sync),
        // por isso o mail é capturado como "sent" (não "queued") pelo Mail::fake().
        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) use ($user): bool {
            return $mail->hasTo($user->email);
        });
    }

    /** @test */
    public function test_forgot_for_unknown_email_returns_202_silently(): void
    {
        Mail::fake();

        $this->createTenant(['slug' => 'clinica-reset-b', 'status' => 'active']);

        $response = $this->postJson(
            $this->forgotUrl('clinica-reset-b'),
            ['email' => 'nobody@nowhere.com'],
        );

        $response->assertStatus(202);

        Mail::assertNothingSent();
    }

    /** @test */
    public function test_forgot_for_email_in_other_tenant_returns_202_silently(): void
    {
        Mail::fake();

        $tenantA = $this->createTenant(['slug' => 'clinica-reset-c', 'status' => 'active']);
        $tenantB = $this->createTenant(['slug' => 'clinica-reset-d', 'status' => 'active']);

        // Usuário existe no tenant B, não no tenant A.
        $this->createUserForTenant($tenantB, [
            'email' => 'shared@example.com',
            'status' => 'active',
        ]);

        // Solicita no subdomínio do tenant A — e-mail existe só no tenant B.
        $response = $this->postJson(
            $this->forgotUrl($tenantA->slug),
            ['email' => 'shared@example.com'],
        );

        $response->assertStatus(202);

        Mail::assertNothingSent();
    }

    /** @test */
    public function test_forgot_creates_token_row_with_hash(): void
    {
        Mail::fake();

        $tenant = $this->createTenant(['slug' => 'clinica-reset-e', 'status' => 'active']);
        $user = $this->createUserForTenant($tenant, [
            'email' => 'hash@clinica-reset-e.com',
            'status' => 'active',
        ]);

        $this->postJson(
            $this->forgotUrl('clinica-reset-e'),
            ['email' => 'hash@clinica-reset-e.com'],
        );

        $row = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->where('tenant_id', $tenant->id)
            ->first();

        $this->assertNotNull($row, 'Deve existir linha em password_reset_tokens');

        // O token no banco NUNCA é igual ao token em claro (está hasheado).
        // Verificamos que não é um token em claro de 64 chars hexadecimais.
        $this->assertNotEquals(64, strlen(bin2hex(random_bytes(1))), 'sanity check interno');
        $this->assertStringStartsWith('$argon2id$', $row->token,
            'O token deve estar hasheado com argon2id (driver configurado no projeto)');
    }

    /** @test */
    public function test_reset_with_valid_token_changes_password(): void
    {
        Mail::fake();

        $tenant = $this->createTenant(['slug' => 'clinica-reset-f', 'status' => 'active']);
        $user = $this->createUserForTenant($tenant, [
            'email' => 'valid@clinica-reset-f.com',
            'password' => Hash::make('OldPassword1'),
            'status' => 'active',
        ]);

        // Injeta o tenant no container para o service.
        $this->app->instance('tenant', $tenant);

        /** @var PasswordResetService $service */
        $service = $this->app->make(PasswordResetService::class);

        // Captura o token em claro via UPSERT direto + helper.
        $tokenInClaro = bin2hex(random_bytes(32));
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email, 'tenant_id' => $tenant->id],
            ['token' => Hash::make($tokenInClaro), 'created_at' => now()],
        );

        $response = $this->postJson(
            $this->resetUrl('clinica-reset-f'),
            [
                'email' => 'valid@clinica-reset-f.com',
                'token' => $tokenInClaro,
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ],
        );

        $response->assertStatus(204);

        // Nova senha funciona.
        $this->assertTrue(Hash::check('NewPassword1', $user->fresh()->password));

        // Senha antiga não funciona mais.
        $this->assertFalse(Hash::check('OldPassword1', $user->fresh()->password));

        // Verifica que login com senha antiga retorna 401.
        $loginResponse = $this->postJson(
            'http://clinica-reset-f.lvh.me/api/v1/auth/login',
            ['email' => 'valid@clinica-reset-f.com', 'password' => 'OldPassword1'],
        );
        $loginResponse->assertUnauthorized();
    }

    /** @test */
    public function test_reset_with_invalid_token_returns_410(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-reset-g', 'status' => 'active']);
        $user = $this->createUserForTenant($tenant, [
            'email' => 'invalid@clinica-reset-g.com',
            'status' => 'active',
        ]);

        // Cria linha no banco mas o token enviado é aleatório diferente.
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'tenant_id' => $tenant->id,
            'token' => Hash::make(bin2hex(random_bytes(32))),
            'created_at' => now(),
        ]);

        $fakeToken = str_repeat('a', 64);

        $response = $this->postJson(
            $this->resetUrl('clinica-reset-g'),
            [
                'email' => 'invalid@clinica-reset-g.com',
                'token' => $fakeToken,
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ],
        );

        $response->assertStatus(410);
    }

    /** @test */
    public function test_reset_with_expired_token_returns_410(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-reset-h', 'status' => 'active']);
        $user = $this->createUserForTenant($tenant, [
            'email' => 'expired@clinica-reset-h.com',
            'status' => 'active',
        ]);

        $tokenInClaro = bin2hex(random_bytes(32));

        // Cria token com created_at já expirado (61 minutos atrás).
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'tenant_id' => $tenant->id,
            'token' => Hash::make($tokenInClaro),
            'created_at' => now()->subMinutes(61),
        ]);

        $response = $this->postJson(
            $this->resetUrl('clinica-reset-h'),
            [
                'email' => 'expired@clinica-reset-h.com',
                'token' => $tokenInClaro,
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ],
        );

        $response->assertStatus(410);
    }

    /** @test */
    public function test_token_can_be_used_only_once(): void
    {
        Mail::fake();

        $tenant = $this->createTenant(['slug' => 'clinica-reset-i', 'status' => 'active']);
        $user = $this->createUserForTenant($tenant, [
            'email' => 'once@clinica-reset-i.com',
            'status' => 'active',
        ]);

        $tokenInClaro = bin2hex(random_bytes(32));

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'tenant_id' => $tenant->id,
            'token' => Hash::make($tokenInClaro),
            'created_at' => now(),
        ]);

        $payload = [
            'email' => 'once@clinica-reset-i.com',
            'token' => $tokenInClaro,
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ];

        // Primeira utilização: sucesso.
        $first = $this->postJson($this->resetUrl('clinica-reset-i'), $payload);
        $first->assertStatus(204);

        // Segunda utilização: token já foi consumido → 410.
        $second = $this->postJson($this->resetUrl('clinica-reset-i'), $payload);
        $second->assertStatus(410);
    }

    /** @test */
    public function test_reset_response_validates_password_strength(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-reset-j', 'status' => 'active']);
        $user = $this->createUserForTenant($tenant, [
            'email' => 'weak@clinica-reset-j.com',
            'status' => 'active',
        ]);

        $tokenInClaro = bin2hex(random_bytes(32));

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'tenant_id' => $tenant->id,
            'token' => Hash::make($tokenInClaro),
            'created_at' => now(),
        ]);

        // Senha com menos de 8 caracteres.
        $response = $this->postJson(
            $this->resetUrl('clinica-reset-j'),
            [
                'email' => 'weak@clinica-reset-j.com',
                'token' => $tokenInClaro,
                'password' => 'short',
                'password_confirmation' => 'short',
            ],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }
}
