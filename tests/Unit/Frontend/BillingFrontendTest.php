<?php

namespace Tests\Unit\Frontend;

use Tests\TestCase;

/**
 * Smoke check estático do frontend de Billing (T190).
 *
 * Verifica a integridade dos artefatos JS/Vue sem executar o runtime Vue.
 * Serve como gate de CI antes do `vite build`.
 *
 * Cobre:
 *   - PlansPage.vue existe e referencia a rota /billing/plans.
 *   - PlansPage.vue usa formatCurrency.
 *   - PlansPage.vue redireciona para Stripe via window.location.href e checkout_url.
 *   - SubscriptionPage.vue existe e chama /billing/subscription.
 *   - SubscriptionPage.vue trata 404 (estado trial sem assinatura).
 *   - Router declara billing.plans e billing.subscription.
 */
class BillingFrontendTest extends TestCase
{
    private string $plansPagePath;

    private string $subscriptionPagePath;

    private string $routerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plansPagePath = base_path('resources/js/pages/billing/PlansPage.vue');
        $this->subscriptionPagePath = base_path('resources/js/pages/billing/SubscriptionPage.vue');
        $this->routerPath = base_path('resources/js/router/index.js');
    }

    // ── PlansPage — existência ────────────────────────────────────────────────

    public function test_plans_page_exists(): void
    {
        $this->assertFileExists(
            $this->plansPagePath,
            'resources/js/pages/billing/PlansPage.vue deve existir (T190).'
        );
    }

    // ── PlansPage — chama GET /billing/plans ─────────────────────────────────

    public function test_plans_page_calls_get_plans(): void
    {
        $vue = file_get_contents($this->plansPagePath);

        $this->assertStringContainsString(
            '/billing/plans',
            $vue,
            'PlansPage.vue deve chamar GET /billing/plans.'
        );
    }

    // ── PlansPage — usa formatCurrency ────────────────────────────────────────

    public function test_plans_page_uses_format_currency(): void
    {
        $vue = file_get_contents($this->plansPagePath);

        $this->assertStringContainsString(
            'formatCurrency',
            $vue,
            'PlansPage.vue deve usar formatCurrency para exibir o preço do plano.'
        );
    }

    // ── PlansPage — redireciona para Stripe via window.location.href ──────────

    public function test_checkout_redirects_to_stripe_url(): void
    {
        $vue = file_get_contents($this->plansPagePath);

        $this->assertStringContainsString(
            'window.location.href',
            $vue,
            'PlansPage.vue deve redirecionar para o Stripe via window.location.href.'
        );
        $this->assertStringContainsString(
            'checkout_url',
            $vue,
            'PlansPage.vue deve usar checkout_url recebido da API para redirecionar.'
        );
    }

    // ── SubscriptionPage — existência ─────────────────────────────────────────

    public function test_subscription_page_exists(): void
    {
        $this->assertFileExists(
            $this->subscriptionPagePath,
            'resources/js/pages/billing/SubscriptionPage.vue deve existir (T190).'
        );
    }

    // ── SubscriptionPage — trata 404 mostrando estado trial ──────────────────

    public function test_subscription_page_handles_404_trial(): void
    {
        $vue = file_get_contents($this->subscriptionPagePath);

        $this->assertTrue(
            str_contains($vue, '404') || str_contains($vue, 'noSubscription') || str_contains($vue, 'no_subscription'),
            'SubscriptionPage.vue deve tratar 404 exibindo o estado de trial sem assinatura.'
        );
    }

    // ── SubscriptionPage — chama GET /billing/subscription ───────────────────

    public function test_subscription_page_calls_get_subscription(): void
    {
        $vue = file_get_contents($this->subscriptionPagePath);

        $this->assertStringContainsString(
            '/billing/subscription',
            $vue,
            'SubscriptionPage.vue deve chamar GET /billing/subscription.'
        );
    }

    // ── Router — declara billing.plans e billing.subscription ────────────────

    public function test_router_has_billing_routes(): void
    {
        $js = file_get_contents($this->routerPath);

        $this->assertStringContainsString(
            "name: 'billing.plans'",
            $js,
            "router/index.js deve declarar rota com name: 'billing.plans'."
        );
        $this->assertStringContainsString(
            "name: 'billing.subscription'",
            $js,
            "router/index.js deve declarar rota com name: 'billing.subscription'."
        );
    }
}
