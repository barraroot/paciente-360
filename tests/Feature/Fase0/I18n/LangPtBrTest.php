<?php

namespace Tests\Feature\Fase0\I18n;

use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * Testes de localização pt-BR (T075 — Princípio: Localização).
 *
 * Verifica que os arquivos de tradução pt-BR estão presentes e retornam
 * as strings corretas para auth, validation, tenant e billing.
 *
 * @see lang/pt_BR/auth.php
 * @see lang/pt_BR/validation.php
 * @see lang/pt_BR/tenant.php
 * @see lang/pt_BR/billing.php
 */
class LangPtBrTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        App::setLocale('pt_BR');
    }

    /** @test */
    public function test_auth_failed_returns_pt_br(): void
    {
        $this->assertSame('Credenciais inválidas.', __('auth.failed'));
    }

    /** @test */
    public function test_validation_required_returns_pt_br(): void
    {
        $this->assertSame(
            'O campo e-mail é obrigatório.',
            __('validation.required', ['attribute' => 'e-mail'])
        );
    }

    /** @test */
    public function test_tenant_register_success_translation_exists(): void
    {
        $this->assertSame(
            'Clínica cadastrada com sucesso. Bem-vindo ao Paciente360!',
            __('tenant.register.success')
        );
    }

    /** @test */
    public function test_billing_plan_starter_translation(): void
    {
        $this->assertSame('Starter', __('billing.plan.starter'));
    }

    /** @test */
    public function test_app_locale_is_pt_br(): void
    {
        $this->assertSame('pt_BR', config('app.locale'));
    }
}
