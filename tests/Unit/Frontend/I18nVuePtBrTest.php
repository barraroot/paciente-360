<?php

namespace Tests\Unit\Frontend;

use Tests\TestCase;

/**
 * Cobre a configuração do Vue I18n para pt-BR (T076).
 *
 * Verifica que o arquivo `resources/js/i18n/pt-BR.json` possui as
 * chaves namespacadas necessárias, que a instância `createI18n` está
 * corretamente configurada em `index.js`, que o plugin está registrado
 * em `app.js` e que o composable `useI18nFormat` usa as APIs Intl nativas.
 *
 * Estes testes inspecionam o source dos arquivos JS/JSON — o runtime Vue
 * não executa em PHPUnit. Servem como gate de CI antes do `vite build`.
 */
class I18nVuePtBrTest extends TestCase
{
    private string $jsonPath;

    private string $indexPath;

    private string $appJsPath;

    private string $composablePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonPath = base_path('resources/js/i18n/pt-BR.json');
        $this->indexPath = base_path('resources/js/i18n/index.js');
        $this->appJsPath = base_path('resources/js/app.js');
        $this->composablePath = base_path('resources/js/composables/useI18nFormat.js');
    }

    public function test_pt_br_json_is_valid(): void
    {
        $this->assertFileExists($this->jsonPath, 'pt-BR.json deve existir.');

        $raw = file_get_contents($this->jsonPath);
        $data = json_decode($raw, true);

        $this->assertIsArray($data, 'pt-BR.json deve ser JSON válido.');

        foreach (['auth', 'tenant', 'billing', 'users', 'audit', 'common'] as $key) {
            $this->assertArrayHasKey($key, $data, "pt-BR.json deve ter chave raiz \"{$key}\".");
        }
    }

    public function test_pt_br_json_has_login_title(): void
    {
        $raw = file_get_contents($this->jsonPath);
        $data = json_decode($raw, true);

        $this->assertSame(
            'Entrar',
            $data['auth']['login']['title'],
            'auth.login.title deve ser "Entrar".',
        );
    }

    public function test_i18n_index_js_creates_instance(): void
    {
        $this->assertFileExists($this->indexPath, 'resources/js/i18n/index.js deve existir.');

        $js = file_get_contents($this->indexPath);

        $this->assertStringContainsString('createI18n', $js, 'index.js deve usar createI18n.');
        $this->assertStringContainsString("'pt-BR'", $js, "index.js deve referenciar 'pt-BR'.");
        $this->assertStringContainsString('legacy: false', $js, 'index.js deve ter legacy: false (Composition API mode).');
    }

    public function test_app_js_uses_i18n(): void
    {
        $this->assertFileExists($this->appJsPath, 'resources/js/app.js deve existir.');

        $js = file_get_contents($this->appJsPath);

        $this->assertMatchesRegularExpression(
            '/app\.use\s*\(\s*i18n\s*\)/',
            $js,
            'app.js deve registrar o plugin i18n via app.use(i18n).',
        );
    }

    public function test_composable_format_uses_intl(): void
    {
        $this->assertFileExists($this->composablePath, 'useI18nFormat.js deve existir.');

        $js = file_get_contents($this->composablePath);

        $this->assertStringContainsString(
            'Intl.DateTimeFormat',
            $js,
            'useI18nFormat.js deve usar Intl.DateTimeFormat para formatação de datas.',
        );
        $this->assertStringContainsString(
            'Intl.NumberFormat',
            $js,
            'useI18nFormat.js deve usar Intl.NumberFormat para formatação de números e moeda.',
        );
    }
}
