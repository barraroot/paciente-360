<?php

namespace Tests\Unit\Frontend;

use Tests\TestCase;

/**
 * Smoke check estático do frontend do Onboarding Wizard (T163).
 *
 * Verifica a integridade dos artefatos JS/Vue sem executar o runtime Vue.
 * Serve como gate de CI antes do `vite build`.
 *
 * Cobre:
 *   - OnboardingWizardPage.vue existe.
 *   - Página usa chaves i18n obrigatórias.
 *   - Página faz fetchState no onMounted com /onboarding/state.
 *   - Página trata steps com status 'locked'.
 *   - Página redireciona ao completar onboarding via state.completed.
 *   - Form de clinic_data possui campos address, phone e opening_hours.
 *   - Router declara rota panel.onboarding.
 */
class OnboardingFrontendTest extends TestCase
{
    private string $pagePath;

    private string $routerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pagePath = base_path('resources/js/pages/onboarding/OnboardingWizardPage.vue');
        $this->routerPath = base_path('resources/js/router/index.js');
    }

    // ── Existência do arquivo ──────────────────────────────────────────────────

    public function test_onboarding_page_exists(): void
    {
        $this->assertFileExists(
            $this->pagePath,
            'resources/js/pages/onboarding/OnboardingWizardPage.vue deve existir (T163).'
        );
    }

    // ── Chaves i18n ───────────────────────────────────────────────────────────

    public function test_page_uses_i18n_keys(): void
    {
        $vue = file_get_contents($this->pagePath);

        $this->assertStringContainsString(
            'tenant.onboarding.title',
            $vue,
            'OnboardingWizardPage deve referenciar tenant.onboarding.title.'
        );
        $this->assertStringContainsString(
            'cta_start',
            $vue,
            'OnboardingWizardPage deve referenciar cta_start.'
        );
        $this->assertStringContainsString(
            'cta_save',
            $vue,
            'OnboardingWizardPage deve referenciar cta_save.'
        );
    }

    // ── fetchState no onMounted ───────────────────────────────────────────────

    public function test_page_fetches_state_on_mount(): void
    {
        $vue = file_get_contents($this->pagePath);

        $this->assertStringContainsString(
            'onMounted',
            $vue,
            'OnboardingWizardPage deve usar onMounted para buscar o state inicial.'
        );
        $this->assertStringContainsString(
            '/onboarding/state',
            $vue,
            'OnboardingWizardPage deve chamar GET /onboarding/state.'
        );
    }

    // ── Tratamento de steps locked ────────────────────────────────────────────

    public function test_page_handles_locked_steps(): void
    {
        $vue = file_get_contents($this->pagePath);

        $this->assertMatchesRegularExpression(
            "/'locked'/",
            $vue,
            "OnboardingWizardPage deve verificar o status 'locked' para desabilitar botão."
        );
    }

    // ── Redirect ao completar ─────────────────────────────────────────────────

    public function test_page_handles_completed_state_with_redirect(): void
    {
        $vue = file_get_contents($this->pagePath);

        $this->assertMatchesRegularExpression(
            '/state(?:\.value)?\.completed/',
            $vue,
            'OnboardingWizardPage deve verificar state.completed (ou state.value.completed) para exibir banner e redirecionar.'
        );
        $this->assertStringContainsString(
            "router.push('/panel')",
            $vue,
            "OnboardingWizardPage deve redirecionar para '/panel' ao concluir onboarding."
        );
    }

    // ── Campos do form clinic_data ────────────────────────────────────────────

    public function test_clinic_data_form_has_required_fields(): void
    {
        $vue = file_get_contents($this->pagePath);

        $this->assertStringContainsString(
            'address',
            $vue,
            'OnboardingWizardPage deve conter campo address no form de clinic_data.'
        );
        $this->assertStringContainsString(
            'phone',
            $vue,
            'OnboardingWizardPage deve conter campo phone no form de clinic_data.'
        );
        $this->assertStringContainsString(
            'opening_hours',
            $vue,
            'OnboardingWizardPage deve conter campo opening_hours no form de clinic_data.'
        );
    }

    // ── Rota no router ────────────────────────────────────────────────────────

    public function test_router_has_onboarding_route(): void
    {
        $js = file_get_contents($this->routerPath);

        $this->assertStringContainsString(
            "name: 'panel.onboarding'",
            $js,
            "router/index.js deve declarar rota com name: 'panel.onboarding'."
        );
        $this->assertStringContainsString(
            "path: '/panel/onboarding'",
            $js,
            "router/index.js deve declarar path: '/panel/onboarding'."
        );
    }
}
