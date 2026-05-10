<?php

namespace Tests\Unit\Frontend;

use Tests\TestCase;

/**
 * Cobre o bootstrap da SPA Vue 3 (T014).
 *
 * O entrypoint `resources/js/app.js` é responsável por instanciar o
 * app Vue, registrar Pinia (estado), Vue Router (navegação SPA) e
 * Vue I18n (Localização — pt-BR como locale default por
 * Princípio Localização da constituição). Os arquivos de router,
 * i18n e store de auth funcionam como esqueletos para as próximas
 * tasks (T016 monta o shell SPA, US-1.x preenche páginas).
 *
 * Estes testes inspecionam o source dos arquivos JS/JSON — o
 * runtime Vue não executa em PHPUnit. Servem como gate de CI antes
 * que o `vite build` rode (T011 executa o build no job `playwright`
 * via `npm run lint`/`test:e2e`).
 */
class Vue3BootstrapTest extends TestCase
{
    private string $appJsPath;

    private string $routerPath;

    private string $i18nPath;

    private string $localePath;

    private string $authStorePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appJsPath = base_path('resources/js/app.js');
        $this->routerPath = base_path('resources/js/router/index.js');
        $this->i18nPath = base_path('resources/js/i18n/index.js');
        $this->localePath = base_path('resources/js/i18n/pt-BR.json');
        $this->authStorePath = base_path('resources/js/stores/auth.js');
    }

    public function test_app_js_exists(): void
    {
        $this->assertFileExists($this->appJsPath, 'resources/js/app.js é o entrypoint Vite.');
    }

    public function test_router_index_exists(): void
    {
        $this->assertFileExists($this->routerPath, 'resources/js/router/index.js deve existir.');
    }

    public function test_i18n_index_exists(): void
    {
        $this->assertFileExists($this->i18nPath, 'resources/js/i18n/index.js deve existir como factory do vue-i18n.');
    }

    public function test_pt_br_locale_exists(): void
    {
        $this->assertFileExists($this->localePath, 'resources/js/i18n/pt-BR.json é o locale default (Princípio Localização).');
    }

    public function test_auth_store_exists(): void
    {
        $this->assertFileExists($this->authStorePath, 'resources/js/stores/auth.js skeleton deve existir.');
    }

    public function test_app_js_creates_vue_app(): void
    {
        $js = file_get_contents($this->appJsPath);

        $this->assertStringContainsString("from 'vue'", $js, 'app.js deve importar de "vue".');
        $this->assertMatchesRegularExpression('/createApp\s*\(/', $js, 'app.js deve chamar createApp().');
        $this->assertMatchesRegularExpression('/\.mount\([\'"]#app[\'"]\)/', $js, 'App deve montar em #app.');
    }

    public function test_app_js_registers_pinia(): void
    {
        $js = file_get_contents($this->appJsPath);

        $this->assertStringContainsString("from 'pinia'", $js, 'app.js deve importar pinia.');
        $this->assertMatchesRegularExpression('/createPinia\s*\(\s*\)/', $js, 'app.js deve criar instância Pinia.');
    }

    public function test_app_js_registers_router(): void
    {
        $js = file_get_contents($this->appJsPath);

        $this->assertStringContainsString('./router', $js, 'app.js deve importar do diretório router.');
    }

    public function test_app_js_registers_i18n(): void
    {
        $js = file_get_contents($this->appJsPath);

        $this->assertStringContainsString('./i18n', $js, 'app.js deve importar do diretório i18n.');
    }

    public function test_app_js_keeps_echo_bootstrap(): void
    {
        // Reverb/Echo já estava no skeleton; não pode regredir (Princípio V — broadcasts).
        $js = file_get_contents($this->appJsPath);

        $this->assertStringContainsString('./echo', $js, 'Bootstrap do Echo (Reverb) deve permanecer importado.');
    }

    public function test_router_uses_html5_history_mode(): void
    {
        $js = file_get_contents($this->routerPath);

        $this->assertStringContainsString("from 'vue-router'", $js, 'router deve importar vue-router.');
        $this->assertMatchesRegularExpression('/createRouter\s*\(/', $js, 'router deve chamar createRouter().');
        $this->assertMatchesRegularExpression('/createWebHistory\s*\(/', $js, 'router deve usar createWebHistory (HTML5 mode).');
    }

    public function test_router_declares_panel_route(): void
    {
        $js = file_get_contents($this->routerPath);

        // Critério de aceitação da T014: rota /panel deve existir como
        // ponto de entrada da SPA autenticada (T016 vai ligar a um
        // catch-all em web.php e o shell blade).
        $this->assertMatchesRegularExpression('/path:\s*[\'"]\/panel[\'"]/', $js, 'Rota /panel deve estar declarada.');
    }

    public function test_i18n_factory_uses_pt_br_default(): void
    {
        $js = file_get_contents($this->i18nPath);

        $this->assertStringContainsString("from 'vue-i18n'", $js, 'i18n deve importar vue-i18n.');
        $this->assertMatchesRegularExpression('/createI18n\s*\(/', $js, 'i18n deve chamar createI18n().');
        $this->assertMatchesRegularExpression('/locale:\s*[\'"]pt-BR[\'"]/', $js, 'Locale default deve ser pt-BR.');
        $this->assertMatchesRegularExpression('/fallbackLocale:\s*[\'"]pt-BR[\'"]/', $js, 'Fallback locale deve ser pt-BR (MVP é Brasil-only).');
    }

    public function test_pt_br_json_is_valid_json(): void
    {
        $raw = file_get_contents($this->localePath);
        $data = json_decode($raw, true);

        $this->assertIsArray($data, 'pt-BR.json deve ser JSON válido.');
        $this->assertArrayHasKey('app', $data, 'JSON deve ter chave raiz "app" para namespacing.');
    }

    public function test_auth_store_uses_pinia_define_store(): void
    {
        $js = file_get_contents($this->authStorePath);

        $this->assertStringContainsString("from 'pinia'", $js, 'auth store deve importar pinia.');
        $this->assertMatchesRegularExpression('/defineStore\s*\(\s*[\'"]auth[\'"]/', $js, 'Store deve ser registrada com id "auth".');
    }
}
