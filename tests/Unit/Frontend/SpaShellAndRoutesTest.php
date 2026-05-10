<?php

namespace Tests\Unit\Frontend;

use Tests\TestCase;

/**
 * Cobre o shell SPA e o roteamento web (T016).
 *
 * Esta task transforma o entrypoint Vue 3 (T014) em uma SPA
 * efetivamente entregue pelo Laravel:
 *
 * 1. `resources/views/app.blade.php` é o shell mínimo HTML que
 *    carrega `@vite(['resources/css/app.css', 'resources/js/app.js'])`
 *    e contém `<div id="app">` (alvo do `mount('#app')` em app.js).
 * 2. `routes/web.php` declara:
 *    - `/` → SPA (router Vue redireciona para `/panel`).
 *    - `/cadastro` → SPA (US-1.1, fluxo público de cadastro de tenant).
 *    - `/panel/{any?}` → catch-all que sempre devolve a SPA, deixando
 *      o Vue Router (HTML5 mode) lidar com sub-rotas no client.
 *    - `/admin` (Filament — entra de fato em T118; aqui só placeholder
 *      explícito que NÃO captura o Filament real).
 *    - `/stripe/webhook` (preenchido em T072; aqui apenas placeholder).
 *
 * Os testes inspecionam o source dos arquivos — runtime Vue não
 * roda em PHPUnit, e os controllers reais só existirão nas próximas
 * fases. Servem como gate de CI antes do `vite build` (T011).
 */
class SpaShellAndRoutesTest extends TestCase
{
    private string $bladePath;

    private string $webRoutesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bladePath = base_path('resources/views/app.blade.php');
        $this->webRoutesPath = base_path('routes/web.php');
    }

    public function test_app_blade_exists(): void
    {
        $this->assertFileExists(
            $this->bladePath,
            'resources/views/app.blade.php deve existir como shell único da SPA.'
        );
    }

    public function test_app_blade_loads_vite_entrypoints(): void
    {
        $blade = file_get_contents($this->bladePath);

        $this->assertStringContainsString(
            '@vite(',
            $blade,
            'Shell deve carregar assets via diretiva @vite.'
        );
        $this->assertStringContainsString(
            'resources/css/app.css',
            $blade,
            'Shell deve declarar resources/css/app.css.'
        );
        $this->assertStringContainsString(
            'resources/js/app.js',
            $blade,
            'Shell deve declarar resources/js/app.js (entrypoint Vue).'
        );
    }

    public function test_app_blade_has_app_mount_target(): void
    {
        $blade = file_get_contents($this->bladePath);

        $this->assertMatchesRegularExpression(
            '/<div\s+id=["\']app["\']/',
            $blade,
            'Shell deve conter <div id="app"> — alvo do createApp(...).mount("#app").'
        );
    }

    public function test_app_blade_uses_locale_from_app(): void
    {
        $blade = file_get_contents($this->bladePath);

        // Princípio Localização: pt-BR no MVP, mas o atributo `lang`
        // deve ser dinâmico para não regredir quando outros locales
        // entrarem.
        $this->assertMatchesRegularExpression(
            '/<html[^>]+lang=["\']\{\{[^}]*app\(\)->getLocale\(\)/',
            $blade,
            'Atributo <html lang="..."> deve ler app()->getLocale().'
        );
    }

    public function test_app_blade_sets_csrf_meta(): void
    {
        $blade = file_get_contents($this->bladePath);

        // Sanctum SPA (T050) exige <meta name="csrf-token"> para o
        // axios bootstrap pegar o token via XSRF-TOKEN cookie + header.
        // Deixamos pronto agora para evitar churn em T050.
        $this->assertMatchesRegularExpression(
            '/<meta\s+name=["\']csrf-token["\']\s+content=["\']\{\{\s*csrf_token\(\)/',
            $blade,
            'Shell deve expor <meta name="csrf-token"> para o axios bootstrap.'
        );
    }

    public function test_web_routes_file_exists(): void
    {
        $this->assertFileExists($this->webRoutesPath, 'routes/web.php deve existir.');
    }

    public function test_web_routes_serves_spa_at_root(): void
    {
        $php = file_get_contents($this->webRoutesPath);

        // Rota raiz devolve o shell SPA; o redirect para /panel
        // acontece no Vue Router (createWebHistory + redirect em
        // resources/js/router/index.js).
        $this->assertMatchesRegularExpression(
            '/Route::view\s*\(\s*[\'"]\/[\'"]\s*,\s*[\'"]app[\'"]/',
            $php,
            'Rota / deve ser Route::view("/", "app").'
        );
    }

    public function test_web_routes_serves_spa_on_cadastro(): void
    {
        $php = file_get_contents($this->webRoutesPath);

        // /cadastro entra em US-1.1 (cadastro público de tenant).
        // Fica dentro da SPA (mesmo shell), Vue Router decide a tela.
        $this->assertMatchesRegularExpression(
            '/Route::view\s*\(\s*[\'"]\/cadastro[\'"]\s*,\s*[\'"]app[\'"]/',
            $php,
            'Rota /cadastro deve ser Route::view("/cadastro", "app").'
        );
    }

    public function test_web_routes_has_panel_catch_all(): void
    {
        $php = file_get_contents($this->webRoutesPath);

        // Catch-all para HTML5 mode do Vue Router. Sem isso, GET
        // /panel/foo retorna 404 do Laravel em vez do shell SPA.
        // Critério da task: `GET /panel/foo` e `GET /panel/foo/bar`
        // devolvem a SPA.
        $this->assertMatchesRegularExpression(
            '/Route::view\s*\(\s*[\'"]\/panel\/?\{any\??\}[^\'"]*[\'"]\s*,\s*[\'"]app[\'"]\)/',
            $php,
            'Rota /panel/{any?} deve servir o shell SPA (catch-all).'
        );
        $this->assertMatchesRegularExpression(
            '/->where\(\s*[\'"]any[\'"]\s*,\s*[\'"]\.\*[\'"]\s*\)/',
            $php,
            'Catch-all do /panel deve aceitar qualquer profundidade via where("any", ".*").'
        );
    }

    public function test_web_routes_reserves_panel_root(): void
    {
        $php = file_get_contents($this->webRoutesPath);

        // /panel sem path adicional também precisa cair no shell SPA
        // (antes do catch-all garantir as profundas).
        $this->assertMatchesRegularExpression(
            '/Route::view\s*\(\s*[\'"]\/panel[\'"]\s*,\s*[\'"]app[\'"]/',
            $php,
            'Rota /panel (sem sufixo) deve servir o shell SPA.'
        );
    }

    public function test_web_routes_reserves_admin_path(): void
    {
        $php = file_get_contents($this->webRoutesPath);

        // /admin é território do Filament (T118). Aqui apenas marcamos
        // o slug como reservado em comentário para que ninguém
        // adicione uma rota concorrente. O teste valida o comentário
        // âncora.
        $this->assertMatchesRegularExpression(
            '/T118.*Filament|Filament.*T118/i',
            $php,
            '/admin deve estar reservado para Filament em T118 (comentário-âncora).'
        );
    }

    public function test_web_routes_reserves_stripe_webhook(): void
    {
        $php = file_get_contents($this->webRoutesPath);

        // /stripe/webhook é território do Cashier (T072). Mesmo motivo:
        // comentário-âncora para evitar conflito futuro.
        $this->assertMatchesRegularExpression(
            '/T072.*[Ss]tripe|[Ss]tripe.*T072/',
            $php,
            '/stripe/webhook deve estar reservado para Cashier em T072 (comentário-âncora).'
        );
    }

    public function test_root_route_returns_spa_shell(): void
    {
        // Smoke test funcional: a rota / efetivamente devolve o shell
        // com o id="app". Não chamamos `@vite()` no contexto de teste
        // (sem manifest), então usamos `withoutVite()` no setup do
        // roteador via app.blade — mas mais simples: garantimos via
        // `$this->withoutVite()` neste único teste.
        $this->withoutVite();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="app"', false);
    }

    public function test_panel_catch_all_returns_spa_shell(): void
    {
        // Critério de aceitação direto da task T016.
        $this->withoutVite();

        $this->get('/panel')->assertOk()->assertSee('id="app"', false);
        $this->get('/panel/foo')->assertOk()->assertSee('id="app"', false);
        $this->get('/panel/foo/bar')->assertOk()->assertSee('id="app"', false);
    }

    public function test_cadastro_route_returns_spa_shell(): void
    {
        $this->withoutVite();

        $this->get('/cadastro')->assertOk()->assertSee('id="app"', false);
    }
}
