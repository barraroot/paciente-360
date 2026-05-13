<?php

namespace Tests\Feature\Fase4\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * T065 — CORS preflight em rotas API + broadcasting (US4 — AC-A.4.x).
 *
 * Valida que o middleware `HandleCors` (auto-registrado pelo Laravel 11+)
 * usa `config/cors.php` corretamente:
 *  - Origens whitelisted recebem headers Access-Control-Allow-Origin no preflight
 *  - Origens fora da whitelist NÃO recebem headers (browser bloqueia)
 *  - max-age = 3600s cacheável
 *  - paths cobrem `api/*` e `broadcasting/auth`
 *
 * @see config/cors.php
 * @see specs/004-token-auth-migration/spec.md §AC-A.4.x
 */
class CorsPreflightTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Origem na allow-list (config/cors.php default em test/dev).
     */
    private const ALLOWED_ORIGIN = 'http://localhost:5173';

    /**
     * Origem fora da allow-list (não bate com `allowed_origins` nem com
     * `allowed_origins_patterns`).
     */
    private const DISALLOWED_ORIGIN = 'http://attacker.example.com';

    #[Test]
    public function options_preflight_returns_cors_headers_for_allowed_origin(): void
    {
        $response = $this->call('OPTIONS', '/api/v1/auth/me', [], [], [], [
            'HTTP_ORIGIN' => self::ALLOWED_ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Authorization, X-Tenant-Slug',
        ]);

        $response->assertNoContent(204);
        $this->assertSame(self::ALLOWED_ORIGIN, $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotNull($response->headers->get('Access-Control-Allow-Methods'));
    }

    #[Test]
    public function options_preflight_no_headers_for_disallowed_origin(): void
    {
        $response = $this->call('OPTIONS', '/api/v1/auth/me', [], [], [], [
            'HTTP_ORIGIN' => self::DISALLOWED_ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        // O middleware HandleCors do Laravel devolve a request normalmente para
        // origens não whitelisted, mas SEM o header Access-Control-Allow-Origin.
        // Sem esse header, o browser bloqueia a request — o servidor não
        // "rejeita" explicitamente (apenas omite o header).
        $this->assertNull(
            $response->headers->get('Access-Control-Allow-Origin'),
            'Origem não whitelisted não deve receber Access-Control-Allow-Origin',
        );
    }

    #[Test]
    public function preflight_includes_max_age_3600(): void
    {
        $response = $this->call('OPTIONS', '/api/v1/auth/me', [], [], [], [
            'HTTP_ORIGIN' => self::ALLOWED_ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        $this->assertSame('3600', $response->headers->get('Access-Control-Max-Age'));
    }

    #[Test]
    public function broadcasting_auth_included_in_cors_paths(): void
    {
        $response = $this->call('OPTIONS', '/broadcasting/auth', [], [], [], [
            'HTTP_ORIGIN' => self::ALLOWED_ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Authorization, X-Tenant-Slug',
        ]);

        $response->assertNoContent(204);
        $this->assertSame(self::ALLOWED_ORIGIN, $response->headers->get('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function tenant_subdomain_origin_pattern_is_allowed(): void
    {
        // `allowed_origins_patterns` cobre *.lvh.me e *.crm.com.br (config/cors.php).
        // SPA do tenant em prod fica em app.crm.com.br e em dev em qualquer.lvh.me.
        $response = $this->call('OPTIONS', '/api/v1/auth/me', [], [], [], [
            'HTTP_ORIGIN' => 'https://clinica-x.crm.com.br',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $this->assertSame(
            'https://clinica-x.crm.com.br',
            $response->headers->get('Access-Control-Allow-Origin'),
        );
    }
}
