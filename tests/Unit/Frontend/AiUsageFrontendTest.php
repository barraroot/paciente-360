<?php

namespace Tests\Unit\Frontend;

use Tests\TestCase;

/**
 * Smoke check estático do frontend de AI Usage (T225).
 *
 * Verifica a integridade dos artefatos JS/Vue sem executar o runtime Vue.
 * Serve como gate de CI antes do `vite build`.
 *
 * Cobre:
 *   - AiUsagePage.vue existe.
 *   - AiUsagePage.vue chama GET /billing/ai-usage.
 *   - AiUsagePage.vue usa formatNumber e formatCurrency.
 *   - AiUsagePage.vue exibe alerta quando hard_cap_triggered_at está presente.
 *   - AiUsagePage.vue suporta remoção de hard cap (null / removeCap).
 *   - Router declara billing.ai-usage.
 *   - AiUsagePage.vue usa ConfirmModal para guardar hard_cap = 0.
 */
class AiUsageFrontendTest extends TestCase
{
    private string $aiUsagePagePath;

    private string $routerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aiUsagePagePath = base_path('resources/js/pages/billing/AiUsagePage.vue');
        $this->routerPath = base_path('resources/js/router/index.js');
    }

    // ── AiUsagePage — existência ──────────────────────────────────────────────

    public function test_ai_usage_page_exists(): void
    {
        $this->assertFileExists(
            $this->aiUsagePagePath,
            'resources/js/pages/billing/AiUsagePage.vue deve existir (T225).'
        );
    }

    // ── AiUsagePage — chama GET /billing/ai-usage ─────────────────────────────

    public function test_page_calls_get_ai_usage(): void
    {
        $vue = file_get_contents($this->aiUsagePagePath);

        $this->assertStringContainsString(
            '/billing/ai-usage',
            $vue,
            'AiUsagePage.vue deve chamar GET /billing/ai-usage.'
        );
    }

    // ── AiUsagePage — usa formatNumber e formatCurrency ───────────────────────

    public function test_page_uses_format_number_and_currency(): void
    {
        $vue = file_get_contents($this->aiUsagePagePath);

        $this->assertStringContainsString(
            'formatNumber',
            $vue,
            'AiUsagePage.vue deve usar formatNumber para exibir métricas de mensagens.'
        );
        $this->assertStringContainsString(
            'formatCurrency',
            $vue,
            'AiUsagePage.vue deve usar formatCurrency para exibir custos monetários.'
        );
    }

    // ── AiUsagePage — exibe alerta quando hard_cap_triggered_at presente ──────

    public function test_page_shows_hard_cap_warning_when_triggered(): void
    {
        $vue = file_get_contents($this->aiUsagePagePath);

        $this->assertStringContainsString(
            'hard_cap_triggered_at',
            $vue,
            'AiUsagePage.vue deve verificar hard_cap_triggered_at para exibir alerta de cap atingido.'
        );
    }

    // ── AiUsagePage — suporta remoção de hard cap (null / removeCap) ──────────

    public function test_page_supports_null_hard_cap(): void
    {
        $vue = file_get_contents($this->aiUsagePagePath);

        $this->assertTrue(
            str_contains($vue, 'null') || str_contains($vue, 'removeCap') || str_contains($vue, 'Remover'),
            'AiUsagePage.vue deve suportar hard_cap null (remover limite).'
        );
    }

    // ── Router — declara billing.ai-usage ────────────────────────────────────

    public function test_router_has_ai_usage_route(): void
    {
        $js = file_get_contents($this->routerPath);

        $this->assertStringContainsString(
            "name: 'billing.ai-usage'",
            $js,
            "router/index.js deve declarar rota com name: 'billing.ai-usage'."
        );
    }

    // ── AiUsagePage — usa ConfirmModal para hard_cap = 0 ─────────────────────

    public function test_page_uses_confirm_modal_for_zero_cap(): void
    {
        $vue = file_get_contents($this->aiUsagePagePath);

        $this->assertTrue(
            str_contains($vue, 'ConfirmModal') || str_contains($vue, 'showZeroConfirm'),
            'AiUsagePage.vue deve usar ConfirmModal (ou guarda equivalente) ao definir hard_cap = 0.'
        );
    }
}
