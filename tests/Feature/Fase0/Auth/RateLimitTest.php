<?php

namespace Tests\Feature\Fase0\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Testes de rate limiting (T051 — Princípio VII: Segurança Operacional).
 *
 * Verifica que os limiters nomeados bloqueiam corretamente após
 * exceder o número de tentativas configurado, retornando 429 com
 * o header `Retry-After`.
 *
 * @see App\Providers\RouteServiceProvider::configureRateLimiters()
 * @see specs/001-fundacao-multitenant/spec.md — RNF-009 (rate limit por tenant)
 */
class RateLimitTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Limpa todos os contadores de rate limit antes de cada teste
        // para garantir isolamento entre testes.
        RateLimiter::clear('login');
        RateLimiter::clear('tenant-register');
        RateLimiter::clear('password-forgot');
    }

    /**
     * Após 5 tentativas de login, a 6ª deve retornar 429 com Retry-After.
     *
     * As 5 primeiras retornam 401 (credenciais inválidas, comportamento real
     * do LoginController) — o rate limiter apenas conta os hits, não o status.
     */
    public function test_login_returns_429_after_5_attempts(): void
    {
        // Precisa de um tenant resolvido pelo subdomínio para o LoginController funcionar.
        $this->createTenant(['slug' => 'rate-test', 'status' => 'active']);

        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('http://rate-test.lvh.me/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrong',
            ]);

            // 401 = credenciais inválidas (rate limiter ainda não bloqueou).
            $response->assertStatus(401);
        }

        $response = $this->postJson('http://rate-test.lvh.me/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(429);
        $this->assertTrue(
            $response->headers->has('Retry-After'),
            'Resposta 429 deve incluir o header Retry-After.'
        );
    }

    /**
     * Após 3 tentativas de registro de tenant por hora, a 4ª deve
     * retornar 429 (T143 — endpoint público real `POST /tenants/register`).
     *
     * Usa payloads inválidos (422) para isolar o teste do limiter — o
     * `tenant-register` conta TODOS os hits, não apenas sucessos. Cobertura
     * do happy path com 4 cadastros válidos consecutivos é feita em
     * `RegisterTenantValidationTest::test_rate_limit_3_per_hour_per_ip`.
     */
    public function test_tenant_register_limited_to_3_per_hour(): void
    {
        $url = 'http://crm.lvh.me/api/v1/tenants/register';

        for ($i = 1; $i <= 3; $i++) {
            $response = $this->postJson($url, []);
            // Payload vazio → 422 (validação). Limiter conta o hit.
            $response->assertStatus(422);
        }

        $response = $this->postJson($url, []);

        $response->assertStatus(429);
        $this->assertTrue(
            $response->headers->has('Retry-After'),
            'Resposta 429 deve incluir o header Retry-After.'
        );
    }

    /**
     * Após 3 requisições de recuperação de senha com o mesmo e-mail,
     * a 4ª deve retornar 429.
     *
     * Atualizado em T122: rota movida de /auth/forgot-password para
     * /auth/password/forgot; resposta agora é 202 (não revela existência).
     */
    public function test_password_forgot_limited_per_email(): void
    {
        $tenant = $this->createTenant(['slug' => 'rate-forgot', 'status' => 'active']);
        $email = 'doctor@clinica.com';

        for ($i = 1; $i <= 3; $i++) {
            $response = $this->postJson('http://rate-forgot.lvh.me/api/v1/auth/password/forgot', [
                'email' => $email,
            ]);

            // 202 = resposta genérica (não revela existência do e-mail — FR-032).
            $response->assertStatus(202);
        }

        $response = $this->postJson('http://rate-forgot.lvh.me/api/v1/auth/password/forgot', [
            'email' => $email,
        ]);

        $response->assertStatus(429);
        $this->assertTrue(
            $response->headers->has('Retry-After'),
            'Resposta 429 deve incluir o header Retry-After.'
        );
    }
}
