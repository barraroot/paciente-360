<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

class BillingConfigTest extends TestCase
{
    public function test_payment_retries_default_is_three(): void
    {
        // FR-014 / Constituição v1.2.0: tenant transita para Inadimplente
        // após 3 falhas de cobrança.
        $this->assertSame(3, config('billing.payment_retries'));
    }

    public function test_grace_days_default_is_seven(): void
    {
        // FR-014: 7 dias de carência após inadimplência antes da
        // degradação seletiva ("graceful degradation").
        $this->assertSame(7, config('billing.grace_days'));
    }

    public function test_suspension_eligible_days_default_is_thirty_seven(): void
    {
        // FR-014: 7 (carência) + 30 (degradação) = 37 dias totais de
        // inadimplência tornam o tenant elegível para suspensão manual
        // pelo Super Admin.
        $this->assertSame(37, config('billing.suspension_eligible_days'));
    }

    public function test_hard_cap_default_is_null(): void
    {
        // FR-019: hard cap de gasto IA é opt-in pelo Admin Clínica;
        // default null = sem teto (cobrança normal de excedente).
        $this->assertNull(config('billing.hard_cap_default'));
    }

    public function test_stripe_keys_read_from_env(): void
    {
        // Princípio VII — secrets fora do código. Config apenas
        // referencia variáveis de ambiente.
        config([
            'billing.stripe.key' => 'pk_test_overridden',
            'billing.stripe.secret' => 'sk_test_overridden',
            'billing.stripe.webhook_secret' => 'whsec_overridden',
        ]);

        $this->assertSame('pk_test_overridden', config('billing.stripe.key'));
        $this->assertSame('sk_test_overridden', config('billing.stripe.secret'));
        $this->assertSame('whsec_overridden', config('billing.stripe.webhook_secret'));
    }

    public function test_stripe_config_keys_exist(): void
    {
        // Garante que as chaves Stripe estão estruturadas (mesmo que
        // null em ambiente de teste sem secrets).
        $stripe = config('billing.stripe');

        $this->assertIsArray($stripe);
        $this->assertArrayHasKey('key', $stripe);
        $this->assertArrayHasKey('secret', $stripe);
        $this->assertArrayHasKey('webhook_secret', $stripe);
    }

    public function test_currency_default_is_brl(): void
    {
        // Decisão de produto: cobrança em BRL (Brasil-only no MVP).
        $this->assertSame('brl', config('billing.currency'));
    }
}
