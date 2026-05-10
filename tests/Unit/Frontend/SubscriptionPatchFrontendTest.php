<?php

namespace Tests\Unit\Frontend;

use Tests\TestCase;

/**
 * Smoke check estático do frontend de Upgrade/Downgrade (T203).
 *
 * Verifica a integridade dos artefatos JS/Vue sem executar o runtime Vue.
 * Serve como gate de CI antes do `vite build`.
 *
 * Cobre:
 *   - SubscriptionPage.vue contém seção de modificação de profissionais.
 *   - SubscriptionPage.vue contém seção de modificação de plano.
 *   - SubscriptionPage.vue usa PATCH /billing/subscription.
 *   - SubscriptionPage.vue trata proration_preview / amount_due.
 *   - SubscriptionPage.vue trata erro 502 / gateway_error.
 *   - ConfirmModal.vue existe como componente reutilizável.
 *   - SubscriptionPage.vue expõe toggle de proration_behavior.
 */
class SubscriptionPatchFrontendTest extends TestCase
{
    private string $subscriptionPagePath;

    private string $confirmModalPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionPagePath = base_path('resources/js/pages/billing/SubscriptionPage.vue');
        $this->confirmModalPath = base_path('resources/js/components/ui/ConfirmModal.vue');
    }

    // ── Seção de modificação de profissionais ─────────────────────────────────

    public function test_subscription_page_has_modify_professionals_section(): void
    {
        $vue = file_get_contents($this->subscriptionPagePath);

        $this->assertTrue(
            str_contains($vue, 'modify_professionals') || str_contains($vue, 'modify-professionals'),
            'SubscriptionPage.vue deve conter referência a modify_professionals (T203).'
        );
    }

    // ── Seção de modificação de plano ────────────────────────────────────────

    public function test_subscription_page_has_modify_plan_section(): void
    {
        $vue = file_get_contents($this->subscriptionPagePath);

        $this->assertTrue(
            str_contains($vue, 'modify_plan') || str_contains($vue, 'modify-plan'),
            'SubscriptionPage.vue deve conter referência a modify_plan (T203).'
        );
    }

    // ── Usa PATCH /billing/subscription ──────────────────────────────────────

    public function test_subscription_page_uses_patch_endpoint(): void
    {
        $vue = file_get_contents($this->subscriptionPagePath);

        $hasPatch = str_contains($vue, "'PATCH'")
            || str_contains($vue, '"PATCH"')
            || str_contains($vue, 'api.patch')
            || str_contains($vue, '.patch(');

        $hasEndpoint = str_contains($vue, '/billing/subscription');

        $this->assertTrue(
            $hasPatch,
            'SubscriptionPage.vue deve usar PATCH (via api.patch ou método PATCH) para alterar assinatura.'
        );
        $this->assertTrue(
            $hasEndpoint,
            'SubscriptionPage.vue deve referenciar /billing/subscription no PATCH.'
        );
    }

    // ── Trata proration_preview / amount_due ─────────────────────────────────

    public function test_subscription_page_handles_proration_preview(): void
    {
        $vue = file_get_contents($this->subscriptionPagePath);

        $this->assertTrue(
            str_contains($vue, 'proration_preview') || str_contains($vue, 'amount_due'),
            'SubscriptionPage.vue deve tratar proration_preview ou amount_due retornado pelo backend.'
        );
    }

    // ── Trata erro 502 / gateway_error ────────────────────────────────────────

    public function test_subscription_page_handles_502_gateway_error(): void
    {
        $vue = file_get_contents($this->subscriptionPagePath);

        $this->assertTrue(
            str_contains($vue, '502') || str_contains($vue, 'gateway_error'),
            'SubscriptionPage.vue deve tratar status 502 ou exibir mensagem gateway_error.'
        );
    }

    // ── ConfirmModal.vue existe ───────────────────────────────────────────────

    public function test_confirm_modal_component_exists(): void
    {
        $this->assertFileExists(
            $this->confirmModalPath,
            'resources/js/components/ui/ConfirmModal.vue deve existir como componente reutilizável (T203).'
        );
    }

    // ── Toggle de proration_behavior presente ─────────────────────────────────

    public function test_proration_toggle_present(): void
    {
        $vue = file_get_contents($this->subscriptionPagePath);

        $this->assertTrue(
            str_contains($vue, 'proration_behavior') || str_contains($vue, 'toggle_immediate_proration'),
            'SubscriptionPage.vue deve expor toggle de proration_behavior ao usuário.'
        );
    }
}
