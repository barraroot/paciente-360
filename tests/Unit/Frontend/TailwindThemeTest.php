<?php

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Cobre o tema base do Tailwind v4 (T015).
 *
 * Tailwind v4 usa configuração CSS-first via diretiva `@theme` em
 * `resources/css/app.css`. O arquivo `tailwind.config.js` continua
 * existindo como stub para detecção por editores/IDEs e como ponto
 * futuro para plugins JS, mas a fonte de verdade dos tokens é o CSS.
 *
 * Estes testes garantem que os tokens críticos do design system
 * (extraídos do mockup `docs/design/01_Login.png`) permanecem
 * registrados no `@theme` — qualquer remoção quebra o gate de CI
 * antes do build do Vite.
 */
class TailwindThemeTest extends TestCase
{
    private string $cssPath;

    private string $configPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cssPath = base_path('resources/css/app.css');
        $this->configPath = base_path('tailwind.config.js');
    }

    public function test_app_css_exists(): void
    {
        $this->assertFileExists($this->cssPath, 'resources/css/app.css é o entrypoint do Vite.');
    }

    public function test_tailwind_config_js_stub_exists(): void
    {
        // Tailwind v4 dispensa config JS, mas o stub ajuda IDE/lint
        // e fica preparado para plugins futuros (Princípio: Restrições Técnicas).
        $this->assertFileExists($this->configPath, 'tailwind.config.js stub deve existir para detecção por ferramentas.');
    }

    public function test_app_css_imports_tailwind_v4(): void
    {
        $css = file_get_contents($this->cssPath);

        $this->assertStringContainsString("@import 'tailwindcss'", $css, 'CSS deve importar Tailwind v4.');
    }

    public function test_app_css_declares_theme_block(): void
    {
        $css = file_get_contents($this->cssPath);

        $this->assertMatchesRegularExpression('/@theme\s*\{/', $css, 'Bloco @theme deve estar presente.');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function paletteTokensProvider(): array
    {
        return [
            // Primária — verde-escuro/teal extraído do mockup de Login.
            'primary 500' => ['--color-primary-500'],
            'primary 700' => ['--color-primary-700'],
            'primary 900' => ['--color-primary-900'],
            // Neutros baseados em fundo creme quente do mockup.
            'surface base' => ['--color-surface'],
            'surface muted' => ['--color-surface-muted'],
            'border default' => ['--color-border'],
            // Texto.
            'foreground primário' => ['--color-foreground'],
            'foreground muted' => ['--color-foreground-muted'],
            // Estados.
            'success' => ['--color-success-500'],
            'warning' => ['--color-warning-500'],
            'danger' => ['--color-danger-500'],
            'info' => ['--color-info-500'],
        ];
    }

    #[DataProvider('paletteTokensProvider')]
    public function test_app_css_declares_palette_token(string $token): void
    {
        $css = file_get_contents($this->cssPath);

        $this->assertStringContainsString($token, $css, "Token de tema '{$token}' deve estar declarado em @theme.");
    }

    public function test_app_css_declares_font_sans_token(): void
    {
        $css = file_get_contents($this->cssPath);

        $this->assertStringContainsString('--font-sans', $css, 'Token de tipografia --font-sans deve estar definido.');
        $this->assertStringContainsString('Instrument Sans', $css, 'Família tipográfica Instrument Sans deve estar listada (vinculada ao bunny font do Vite).');
    }

    public function test_app_css_uses_oklch_for_palette(): void
    {
        // Tokens extraídos do mockup foram emitidos em oklch — manter a
        // mesma colorimetria evita drift visual entre design e implementação.
        $css = file_get_contents($this->cssPath);

        $this->assertMatchesRegularExpression('/oklch\([^)]+\)/', $css, 'Pelo menos um token de cor deve usar oklch().');
    }

    public function test_tailwind_config_js_exports_module(): void
    {
        $config = file_get_contents($this->configPath);

        // Stub mínimo aceitável: `export default {}` ou `module.exports = {}`.
        $this->assertMatchesRegularExpression('/export\s+default|module\.exports/', $config, 'tailwind.config.js deve exportar um objeto de configuração.');
    }
}
