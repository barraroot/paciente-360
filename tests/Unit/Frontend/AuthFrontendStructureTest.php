<?php

namespace Tests\Unit\Frontend;

use Tests\TestCase;

/**
 * Smoke check estático do frontend de login (T107 / T108).
 *
 * Verifica a integridade dos artefatos JS/Vue sem executar o runtime Vue.
 * Serve como gate de CI antes do `vite build`.
 *
 * Cobre:
 *   - LoginPage.vue existe e usa as chaves i18n obrigatórias.
 *   - auth store possui as actions login, logout e fetchMe.
 *   - router/index.js declara a rota auth.login com meta requiresGuest
 *     e o guard global beforeEach com requiresAuth.
 *   - lib/api.js injeta X-Request-Id e trata respostas 401.
 *   - LoginPage usa layout duas colunas (grid-cols / lg:grid-cols-2).
 */
class AuthFrontendStructureTest extends TestCase
{
    private string $loginPagePath;

    private string $authStorePath;

    private string $routerPath;

    private string $apiPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginPagePath = base_path('resources/js/pages/auth/LoginPage.vue');
        $this->authStorePath = base_path('resources/js/stores/auth.js');
        $this->routerPath = base_path('resources/js/router/index.js');
        $this->apiPath = base_path('resources/js/lib/api.js');
    }

    // ── LoginPage ──────────────────────────────────────────────────────────────

    public function test_login_page_exists(): void
    {
        $this->assertFileExists(
            $this->loginPagePath,
            'resources/js/pages/auth/LoginPage.vue deve existir (T107).'
        );
    }

    public function test_login_page_uses_i18n_keys(): void
    {
        $vue = file_get_contents($this->loginPagePath);

        $this->assertStringContainsString(
            "t('auth.login.title')",
            $vue,
            'LoginPage deve usar t("auth.login.title").'
        );
        $this->assertStringContainsString(
            "t('auth.login.submit')",
            $vue,
            'LoginPage deve usar t("auth.login.submit").'
        );
        $this->assertStringContainsString(
            "t('auth.login.email')",
            $vue,
            'LoginPage deve usar t("auth.login.email").'
        );
    }

    public function test_login_page_has_two_column_layout(): void
    {
        $vue = file_get_contents($this->loginPagePath);

        $this->assertMatchesRegularExpression(
            '/grid[-\w]*cols/',
            $vue,
            'LoginPage deve usar grid com colunas (grid-cols-*).'
        );
        $this->assertStringContainsString(
            'lg:grid-cols-2',
            $vue,
            'LoginPage deve ter layout duas colunas em lg (lg:grid-cols-2).'
        );
    }

    // ── Auth store ─────────────────────────────────────────────────────────────

    public function test_auth_store_has_login_action(): void
    {
        $js = file_get_contents($this->authStorePath);

        $this->assertMatchesRegularExpression(
            '/async\s+login\s*\(/',
            $js,
            'auth.js deve ter a action async login(...).'
        );
        $this->assertMatchesRegularExpression(
            '/async\s+logout\s*\(/',
            $js,
            'auth.js deve ter a action async logout(...).'
        );
        $this->assertMatchesRegularExpression(
            '/async\s+fetchMe\s*\(/',
            $js,
            'auth.js deve ter a action async fetchMe(...).'
        );
    }

    // ── Router ─────────────────────────────────────────────────────────────────

    public function test_router_has_login_route(): void
    {
        $js = file_get_contents($this->routerPath);

        $this->assertStringContainsString(
            "name: 'auth.login'",
            $js,
            "router/index.js deve declarar rota com name: 'auth.login'."
        );
        $this->assertStringContainsString(
            'requiresGuest',
            $js,
            'Rota de login deve ter meta.requiresGuest.'
        );
    }

    public function test_router_has_global_before_each_guard(): void
    {
        $js = file_get_contents($this->routerPath);

        $this->assertStringContainsString(
            'beforeEach',
            $js,
            'router/index.js deve registrar guard global com beforeEach.'
        );
        $this->assertStringContainsString(
            'requiresAuth',
            $js,
            'Guard global deve verificar meta.requiresAuth.'
        );
    }

    // ── api.js ─────────────────────────────────────────────────────────────────

    public function test_api_has_request_id_interceptor(): void
    {
        $js = file_get_contents($this->apiPath);

        $this->assertStringContainsString(
            'X-Request-Id',
            $js,
            'api.js deve injetar header X-Request-Id.'
        );
        $this->assertStringContainsString(
            'interceptors.request.use',
            $js,
            'api.js deve registrar interceptor de request.'
        );
    }

    public function test_api_has_401_response_interceptor(): void
    {
        $js = file_get_contents($this->apiPath);

        $this->assertStringContainsString(
            'interceptors.response.use',
            $js,
            'api.js deve registrar interceptor de response.'
        );
        $this->assertMatchesRegularExpression(
            '/status\s*===\s*401/',
            $js,
            'Interceptor de response deve tratar status 401.'
        );
    }
}
