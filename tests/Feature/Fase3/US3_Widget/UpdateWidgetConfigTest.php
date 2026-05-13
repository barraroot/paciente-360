<?php

namespace Tests\Feature\Fase3\US3_Widget;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Widget\Models\WebWidgetConfig;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T202 — Feature tests para atualização de configuração do widget web.
 *
 * Cobre AC-4.3.5 (comportamento fora de horário) e AC-4.3.6 (autenticação por
 * chave + whitelist de domínios).
 */
class UpdateWidgetConfigTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $adminUser;

    private Channel $webChannel;

    private WebWidgetConfig $widgetConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->adminUser] = $this->tenantAndUserForRole('clinica-widget-upd', 'admin-clinica');

        $this->webChannel = Channel::factory()
            ->forTenant($this->tenant)
            ->web()
            ->create();

        $this->widgetConfig = WebWidgetConfig::factory()
            ->forTenant($this->tenant)
            ->create([
                'channel_id' => $this->webChannel->id,
                'public_key' => str_repeat('a', 64),
            ]);
    }

    private function updateUrl(): string
    {
        return $this->tenantUrl($this->tenant, "/inbox/widget-configs/{$this->webChannel->id}");
    }

    /** @test */
    public function test_admin_can_update_widget_appearance_colors_logo_position(): void
    {
        $payload = [
            'appearance' => [
                'primary_color' => '#2563eb',
                'logo_url' => 'https://clinica.com/logo.png',
                'position' => 'bottom-left',
                'button_label' => 'Fale Conosco',
            ],
        ];

        $response = $this->putJson($this->updateUrl(), $payload);

        $response->assertOk();

        $this->widgetConfig->refresh();
        $this->assertEquals('#2563eb', $this->widgetConfig->appearance['primary_color']);
        $this->assertEquals('https://clinica.com/logo.png', $this->widgetConfig->appearance['logo_url']);
        $this->assertEquals('bottom-left', $this->widgetConfig->appearance['position']);
        $this->assertEquals('Fale Conosco', $this->widgetConfig->appearance['button_label']);
    }

    /** @test */
    public function test_admin_can_set_business_hours_per_weekday(): void
    {
        $payload = [
            'business_hours' => [
                'monday' => '08:00-18:00',
                'tuesday' => '08:00-18:00',
                'wednesday' => '08:00-18:00',
                'thursday' => '08:00-18:00',
                'friday' => '08:00-17:00',
                'saturday' => null,
                'sunday' => null,
                'timezone' => 'America/Sao_Paulo',
            ],
        ];

        $response = $this->putJson($this->updateUrl(), $payload);

        $response->assertOk();

        $this->widgetConfig->refresh();
        $this->assertEquals('08:00-17:00', $this->widgetConfig->business_hours['friday']);
        $this->assertNull($this->widgetConfig->business_hours['saturday']);
    }

    /** @test */
    public function test_outside_hours_behavior_enum_validated(): void
    {
        // Valid values
        foreach (['bloqueia', 'fila', 'normal'] as $value) {
            $response = $this->putJson($this->updateUrl(), [
                'outside_hours_behavior' => $value,
            ]);
            $response->assertOk();
        }

        // Invalid value
        $response = $this->putJson($this->updateUrl(), [
            'outside_hours_behavior' => 'invalido',
        ]);
        $response->assertUnprocessable();
    }

    /** @test */
    public function test_pre_chat_form_enum_validated(): void
    {
        // Valid values
        foreach (['opcional', 'exigido_para_iniciar', 'exigido_para_enviar'] as $value) {
            $response = $this->putJson($this->updateUrl(), [
                'pre_chat_form' => $value,
            ]);
            $response->assertOk();
        }

        // Invalid value
        $response = $this->putJson($this->updateUrl(), [
            'pre_chat_form' => 'outro',
        ]);
        $response->assertUnprocessable();
    }

    /** @test */
    public function test_allowed_origins_array_of_uris(): void
    {
        $payload = [
            'allowed_origins' => [
                'https://clinica-alfa.com.br',
                'https://www.clinica-alfa.com.br',
            ],
        ];

        $response = $this->putJson($this->updateUrl(), $payload);

        $response->assertOk();

        $this->widgetConfig->refresh();
        $this->assertContains('https://clinica-alfa.com.br', $this->widgetConfig->allowed_origins);
        $this->assertContains('https://www.clinica-alfa.com.br', $this->widgetConfig->allowed_origins);
    }

    /** @test */
    public function test_allowed_origins_invalid_url_returns_unprocessable(): void
    {
        $response = $this->putJson($this->updateUrl(), [
            'allowed_origins' => ['not-a-valid-url'],
        ]);

        $response->assertUnprocessable();
    }

    /** @test */
    public function test_cross_tenant_widget_config_returns_404(): void
    {
        // Create another tenant's channel
        [$otherTenant] = $this->tenantAndUserForRole('clinica-outra', 'admin-clinica');

        $otherChannel = Channel::factory()
            ->forTenant($otherTenant)
            ->web()
            ->create();

        WebWidgetConfig::factory()
            ->forTenant($otherTenant)
            ->create(['channel_id' => $otherChannel->id, 'public_key' => str_repeat('b', 64)]);

        // Acting as tenant A admin, try to update tenant B's widget config
        Sanctum::actingAs($this->adminUser, ['*']);
        $this->app->instance('tenant', $this->tenant);

        $response = $this->putJson(
            $this->tenantUrl($this->tenant, "/inbox/widget-configs/{$otherChannel->id}"),
            ['outside_hours_behavior' => 'normal'],
        );

        $response->assertNotFound();
    }
}
