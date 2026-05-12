<?php

namespace Tests\Feature\Fase3\US3_Widget;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Widget\Models\WebWidgetConfig;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T204 — Feature tests para endpoint público de configuração do widget.
 *
 * GET /widget/v1/{public_key}/config
 * Público (sem auth). Valida Origin header contra allowed_origins.
 * Retorna aparência, horários e credenciais Reverb.
 */
class WidgetPublicConfigTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private Channel $webChannel;

    private WebWidgetConfig $widgetConfig;

    private string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('clinica-pub-cfg', 'admin-clinica');

        $this->publicKey = bin2hex(random_bytes(32)); // 64 hex chars

        $this->webChannel = Channel::factory()
            ->forTenant($this->tenant)
            ->web()
            ->create();

        $this->widgetConfig = WebWidgetConfig::factory()
            ->forTenant($this->tenant)
            ->create([
                'channel_id' => $this->webChannel->id,
                'public_key' => $this->publicKey,
                'allowed_origins' => ['https://allowed.com'],
                'appearance' => [
                    'primary_color' => '#1234ab',
                    'position' => 'bottom-right',
                    'button_label' => 'Fale conosco',
                ],
                'initial_message' => 'Olá! Como podemos ajudar?',
                'outside_hours_behavior' => 'fila',
                'pre_chat_form' => 'opcional',
            ]);
    }

    private function configUrl(): string
    {
        return "/widget/v1/{$this->publicKey}/config";
    }

    /** @test */
    public function test_public_config_endpoint_returns_appearance_business_hours_and_reverb_creds(): void
    {
        $response = $this->withHeaders(['Origin' => 'https://allowed.com'])
            ->getJson($this->configUrl());

        $response->assertOk();
        $response->assertJsonStructure([
            'appearance',
            'initial_message',
            'business_hours',
            'outside_hours_behavior',
            'pre_chat_form',
            'reverb' => ['app_key', 'host', 'port', 'scheme'],
        ]);

        $response->assertJsonPath('outside_hours_behavior', 'fila');
        $response->assertJsonPath('pre_chat_form', 'opcional');
    }

    /** @test */
    public function test_public_config_rejects_origin_not_in_whitelist(): void
    {
        $response = $this->withHeaders(['Origin' => 'https://evil.com'])
            ->getJson($this->configUrl());

        $response->assertForbidden();
    }

    /** @test */
    public function test_public_config_accepts_any_origin_when_whitelist_empty(): void
    {
        // Update config to have empty whitelist
        $this->widgetConfig->update(['allowed_origins' => []]);

        $response = $this->withHeaders(['Origin' => 'https://any-origin.com'])
            ->getJson($this->configUrl());

        $response->assertOk();
    }

    /** @test */
    public function test_public_config_returns_404_for_invalid_public_key(): void
    {
        $response = $this->getJson('/widget/v1/'.str_repeat('f', 64).'/config');

        $response->assertNotFound();
    }

    /** @test */
    public function test_public_config_no_auth_required(): void
    {
        // Without any authentication, with allowed origin
        $response = $this->withHeaders(['Origin' => 'https://allowed.com'])
            ->getJson($this->configUrl());

        $response->assertOk();
    }
}
