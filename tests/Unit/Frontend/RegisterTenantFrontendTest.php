<?php

namespace Tests\Unit\Frontend;

use Tests\TestCase;

/**
 * Smoke check estático do frontend de cadastro de tenant (T146).
 *
 * Verifica a integridade dos artefatos JS/Vue sem executar o runtime Vue.
 * Serve como gate de CI antes do `vite build`.
 *
 * Cobre:
 *   - RegisterTenantPage.vue existe.
 *   - Página usa as chaves i18n obrigatórias.
 *   - Página chama getCsrfCookie antes de /tenants/register.
 *   - Redirect cross-subdomínio via window.location.href com login_url.
 *   - Router declara rota tenant.register em /register-clinic.
 *   - Checkbox de termos bloqueia submit quando desmarcado.
 *   - Helper de força de senha presente.
 *   - Máscara de CNPJ aplicada inline via formatCnpj.
 */
class RegisterTenantFrontendTest extends TestCase
{
    private string $pagePath;

    private string $routerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pagePath = base_path('resources/js/pages/tenant-register/RegisterTenantPage.vue');
        $this->routerPath = base_path('resources/js/router/index.js');
    }

    // ── Existência do arquivo ──────────────────────────────────────────────────

    public function test_register_tenant_page_exists(): void
    {
        $this->assertFileExists(
            $this->pagePath,
            'resources/js/pages/tenant-register/RegisterTenantPage.vue deve existir (T146).'
        );
    }

    // ── Chaves i18n obrigatórias ───────────────────────────────────────────────

    public function test_page_includes_required_field_labels(): void
    {
        $vue = file_get_contents($this->pagePath);

        $this->assertStringContainsString(
            "t('tenant.register.title')",
            $vue,
            'RegisterTenantPage deve usar t("tenant.register.title").'
        );
        $this->assertStringContainsString(
            "t('tenant.register.cnpj')",
            $vue,
            'RegisterTenantPage deve usar t("tenant.register.cnpj").'
        );
        // Aceita tanto t('tenant.register.terms_label') quanto keypath="tenant.register.terms_label" (i18n-t)
        $this->assertMatchesRegularExpression(
            '/tenant\.register\.terms_label/',
            $vue,
            'RegisterTenantPage deve referenciar tenant.register.terms_label (via t() ou i18n-t keypath).'
        );
        $this->assertStringContainsString(
            "t('tenant.register.password_help')",
            $vue,
            'RegisterTenantPage deve usar t("tenant.register.password_help").'
        );
    }

    // ── CSRF antes do POST ────────────────────────────────────────────────────

    public function test_page_calls_get_csrf_cookie_before_post(): void
    {
        $vue = file_get_contents($this->pagePath);

        $getCsrfPos = strpos($vue, 'getCsrfCookie');
        $postPos = strpos($vue, "'/tenants/register'");

        $this->assertNotFalse(
            $getCsrfPos,
            'RegisterTenantPage deve chamar api.getCsrfCookie() antes do POST.'
        );
        $this->assertNotFalse(
            $postPos,
            "RegisterTenantPage deve chamar api.post('/tenants/register', ...)."
        );
        $this->assertLessThan(
            $postPos,
            $getCsrfPos,
            'getCsrfCookie() deve aparecer antes de /tenants/register no código.'
        );
    }

    // ── Redirect cross-subdomínio ─────────────────────────────────────────────

    public function test_page_redirects_using_login_url_on_success(): void
    {
        $vue = file_get_contents($this->pagePath);

        $this->assertStringContainsString(
            'window.location.href',
            $vue,
            'RegisterTenantPage deve usar window.location.href para redirect cross-subdomínio.'
        );
        $this->assertStringContainsString(
            'login_url',
            $vue,
            'RegisterTenantPage deve redirecionar para data.login_url retornado pela API.'
        );
    }

    // ── Rota no router ────────────────────────────────────────────────────────

    public function test_router_has_tenant_register_route(): void
    {
        $js = file_get_contents($this->routerPath);

        $this->assertStringContainsString(
            "name: 'tenant.register'",
            $js,
            "router/index.js deve declarar rota com name: 'tenant.register'."
        );
        $this->assertStringContainsString(
            "path: '/register-clinic'",
            $js,
            "router/index.js deve declarar path: '/register-clinic'."
        );
    }

    // ── Bloqueio por termos desmarcados ───────────────────────────────────────

    public function test_terms_checkbox_blocks_submit_when_unchecked(): void
    {
        $vue = file_get_contents($this->pagePath);

        $this->assertMatchesRegularExpression(
            '/!form\.terms_accepted/',
            $vue,
            'RegisterTenantPage deve bloquear submit quando terms_accepted for falso.'
        );
    }

    // ── Indicador de força de senha ───────────────────────────────────────────

    public function test_password_strength_helper_present(): void
    {
        $vue = file_get_contents($this->pagePath);

        $this->assertMatchesRegularExpression(
            '/password_strength/',
            $vue,
            'RegisterTenantPage deve conter referência a password_strength (indicador visual).'
        );
    }

    // ── Máscara de CNPJ inline ────────────────────────────────────────────────

    public function test_cnpj_input_applies_mask(): void
    {
        $vue = file_get_contents($this->pagePath);

        $this->assertStringContainsString(
            'formatCnpj',
            $vue,
            'RegisterTenantPage deve implementar função formatCnpj para máscara de CNPJ.'
        );
    }
}
