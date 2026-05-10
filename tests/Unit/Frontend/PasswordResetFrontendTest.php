<?php

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;

/**
 * Smoke tests estáticos para as páginas de password reset do frontend Vue.
 *
 * Verificam existência de arquivos e presença de strings-chave sem executar
 * o ambiente JavaScript — a intenção é detectar regressões simples (arquivo
 * deletado, chave i18n removida, placeholder não substituído).
 */
class PasswordResetFrontendTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 3).'/resources/js';
    }

    public function test_forgot_password_page_exists(): void
    {
        $this->assertFileExists(
            $this->root.'/pages/auth/ForgotPasswordPage.vue',
        );
    }

    public function test_forgot_page_uses_password_reset_keys(): void
    {
        $contents = file_get_contents($this->root.'/pages/auth/ForgotPasswordPage.vue');

        $this->assertStringContainsString(
            'auth.password_reset.request_title',
            $contents,
        );
        $this->assertStringContainsString(
            'auth.password_reset.email_sent',
            $contents,
        );
        $this->assertStringContainsString(
            'auth.password_reset.request_cta',
            $contents,
        );
    }

    public function test_reset_password_page_exists(): void
    {
        $this->assertFileExists(
            $this->root.'/pages/auth/ResetPasswordPage.vue',
        );
    }

    public function test_reset_page_reads_token_from_route_params(): void
    {
        $contents = file_get_contents($this->root.'/pages/auth/ResetPasswordPage.vue');

        $this->assertTrue(
            str_contains($contents, 'route.params.token')
            || str_contains($contents, 'useRoute().params.token'),
            'ResetPasswordPage deve ler o token de route.params.token',
        );
    }

    public function test_reset_page_reads_email_from_query(): void
    {
        $contents = file_get_contents($this->root.'/pages/auth/ResetPasswordPage.vue');

        $this->assertStringContainsString(
            'route.query.email',
            $contents,
        );
    }

    public function test_router_uses_real_components_for_password_reset(): void
    {
        $contents = file_get_contents($this->root.'/router/index.js');

        $this->assertStringContainsString(
            'pages/auth/ForgotPasswordPage.vue',
            $contents,
            'Router deve importar ForgotPasswordPage.vue (não o placeholder)',
        );
        $this->assertStringContainsString(
            'pages/auth/ResetPasswordPage.vue',
            $contents,
            'Router deve importar ResetPasswordPage.vue (não o placeholder)',
        );

        // Garante que os placeholders foram removidos
        $this->assertStringNotContainsString(
            'TODO T123',
            $contents,
        );
    }

    public function test_auth_hero_panel_extracted(): void
    {
        $this->assertFileExists(
            $this->root.'/components/auth/AuthHeroPanel.vue',
            'AuthHeroPanel.vue deve existir como componente reutilizável',
        );

        $loginContents = file_get_contents($this->root.'/pages/auth/LoginPage.vue');
        $this->assertStringContainsString(
            'AuthHeroPanel',
            $loginContents,
            'LoginPage deve usar o componente AuthHeroPanel',
        );
    }
}
