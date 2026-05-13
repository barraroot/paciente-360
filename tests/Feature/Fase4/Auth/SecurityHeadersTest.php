<?php

namespace Tests\Feature\Fase4\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * T065a — Headers de segurança HTTP (C3 fix — Princípio VII gate).
 *
 * Cobre o middleware `SetSecurityHeaders` (T030) wired no grupo `api` em
 * bootstrap/app.php (Lote G).
 *
 * Headers sempre presentes:
 *  - Strict-Transport-Security (HSTS 1y + includeSubDomains + preload)
 *  - X-Frame-Options: DENY
 *  - X-Content-Type-Options: nosniff
 *  - Referrer-Policy: strict-origin-when-cross-origin
 *
 * CSP por ambiente:
 *  - production: estrita com nonce, sem `unsafe-inline`/`unsafe-eval`
 *  - local/test/staging: permissiva (Vite HMR)
 *
 * @see app/Http/Middleware/SetSecurityHeaders.php
 * @see specs/004-token-auth-migration/spec.md §NC-3
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Faz uma request autenticada a `/api/v1/auth/me` (rota leve e estável)
     * e retorna a response para inspecionar headers.
     */
    private function authenticatedApiResponse(): TestResponse
    {
        $suffix = bin2hex(random_bytes(4));
        $tenant = Tenant::factory()->create(['slug' => 'tenant-sec-'.$suffix]);
        $user = User::factory()->for($tenant)->create([
            'email' => "sec-{$suffix}@test.local",
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        return $this->withHeaders([
            'Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken,
            'X-Tenant-Slug' => $tenant->slug,
        ])->getJson('/api/v1/auth/me');
    }

    // ─── Always-on headers ──────────────────────────────────────────────────

    #[Test]
    public function response_includes_hsts_max_age_1y_include_subdomains(): void
    {
        $hsts = $this->authenticatedApiResponse()->headers->get('Strict-Transport-Security');

        $this->assertNotNull($hsts);
        $this->assertStringContainsString('max-age=31536000', $hsts);
        $this->assertStringContainsString('includeSubDomains', $hsts);
        $this->assertStringContainsString('preload', $hsts);
    }

    #[Test]
    public function response_includes_x_frame_options_deny(): void
    {
        $this->assertSame(
            'DENY',
            $this->authenticatedApiResponse()->headers->get('X-Frame-Options'),
        );
    }

    #[Test]
    public function response_includes_x_content_type_options_nosniff(): void
    {
        $this->assertSame(
            'nosniff',
            $this->authenticatedApiResponse()->headers->get('X-Content-Type-Options'),
        );
    }

    #[Test]
    public function response_includes_referrer_policy_strict_origin(): void
    {
        $referrer = $this->authenticatedApiResponse()->headers->get('Referrer-Policy');

        $this->assertNotNull($referrer);
        // Match `strict-origin-when-cross-origin` ou `strict-origin` (forma curta).
        $this->assertStringContainsString('strict-origin', $referrer);
    }

    // ─── CSP por ambiente ────────────────────────────────────────────────────

    #[Test]
    public function local_env_allows_relaxed_csp_with_unsafe_inline_for_vite_hmr(): void
    {
        // Ambiente de testes (APP_ENV=testing) é tratado como local pelo middleware
        // — não é production.
        $csp = $this->authenticatedApiResponse()->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringContainsString("'unsafe-inline'", $csp);
        $this->assertStringContainsString("'unsafe-eval'", $csp);
        // WS/WSS para HMR + Reverb local
        $this->assertMatchesRegularExpression('/connect-src[^;]*\bws\b/', $csp);
    }

    #[Test]
    public function prod_response_includes_csp_strict_without_unsafe_inline(): void
    {
        $this->app['env'] = 'production';

        $csp = $this->authenticatedApiResponse()->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
    }

    #[Test]
    public function prod_response_includes_csp_strict_without_unsafe_eval(): void
    {
        $this->app['env'] = 'production';

        $csp = $this->authenticatedApiResponse()->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
    }

    #[Test]
    public function prod_response_csp_includes_default_src_self(): void
    {
        $this->app['env'] = 'production';

        $csp = $this->authenticatedApiResponse()->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    #[Test]
    public function csp_nonce_generated_per_request_when_strict(): void
    {
        $this->app['env'] = 'production';

        // Duas requests independentes devem produzir nonces distintos.
        $cspA = $this->authenticatedApiResponse()->headers->get('Content-Security-Policy');
        $cspB = $this->authenticatedApiResponse()->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/'nonce-[a-f0-9]{32}'/", $cspA);
        $this->assertMatchesRegularExpression("/'nonce-[a-f0-9]{32}'/", $cspB);

        // Extrai os nonces e confirma que diferem entre requests.
        preg_match("/'nonce-([a-f0-9]{32})'/", $cspA, $matchA);
        preg_match("/'nonce-([a-f0-9]{32})'/", $cspB, $matchB);

        $this->assertNotSame($matchA[1], $matchB[1], 'Nonces devem ser únicos por request');
    }
}
