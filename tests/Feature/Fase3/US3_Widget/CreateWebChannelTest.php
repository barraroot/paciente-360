<?php

namespace Tests\Feature\Fase3\US3_Widget;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Widget\Models\WebWidgetConfig;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T201 — Feature tests para criação de canal web (widget embutível).
 *
 * Cobre AC-4.3.1: Admin gera snippet personalizável com chave pública.
 * Canal type=web não precisa de credenciais externas — a public_key é
 * gerada internamente.
 */
class CreateWebChannelTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->adminUser] = $this->tenantAndUserForRole('clinica-widget', 'admin-clinica');
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    /** @test */
    public function test_admin_can_create_web_channel_and_generates_public_key(): void
    {
        $payload = [
            'type' => 'web',
            'name' => 'Site Principal',
        ];

        $response = $this->postJson($this->baseUrl('/inbox/channels'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.type', 'web');
        $response->assertJsonPath('data.status', 'ativo');

        // Canal persisted
        $this->assertDatabaseHas('messaging_channels', [
            'tenant_id' => $this->tenant->id,
            'type' => 'web',
            'name' => 'Site Principal',
            'status' => 'ativo',
        ]);

        // WebWidgetConfig created with a 64-char public_key
        $channel = Channel::where('tenant_id', $this->tenant->id)
            ->where('type', 'web')
            ->first();

        $this->assertNotNull($channel);

        $widgetConfig = WebWidgetConfig::where('channel_id', $channel->id)->first();
        $this->assertNotNull($widgetConfig, 'WebWidgetConfig deve ser criada automaticamente');
        $this->assertEquals(64, strlen($widgetConfig->public_key), 'public_key deve ter 64 chars');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $widgetConfig->public_key, 'public_key deve ser hex');
    }

    /** @test */
    public function test_web_channel_creation_does_not_require_credentials(): void
    {
        // Web channel: sem credentials no body
        $payload = [
            'type' => 'web',
            'name' => 'Site de Captação',
        ];

        $response = $this->postJson($this->baseUrl('/inbox/channels'), $payload);

        $response->assertCreated();

        $this->assertDatabaseHas('messaging_channels', [
            'tenant_id' => $this->tenant->id,
            'type' => 'web',
        ]);
    }

    /** @test */
    public function test_public_key_is_globally_unique(): void
    {
        // Create first web channel
        $this->postJson($this->baseUrl('/inbox/channels'), [
            'type' => 'web',
            'name' => 'Site A',
        ])->assertCreated();

        // Create second web channel in same tenant
        $this->postJson($this->baseUrl('/inbox/channels'), [
            'type' => 'web',
            'name' => 'Site B',
        ])->assertCreated();

        $keys = WebWidgetConfig::where('tenant_id', $this->tenant->id)
            ->pluck('public_key')
            ->toArray();

        $this->assertCount(2, $keys);
        $this->assertCount(2, array_unique($keys), 'Cada widget deve ter public_key único');
    }

    /** @test */
    public function test_admin_clinica_ability_channel_connect_required(): void
    {
        // Cria médico sem ability channel.connect
        $medico = $this->createUserForTenant($this->tenant);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->tenant->id);
        $registrar->forgetCachedPermissions();
        $medico->assignRole('medico');

        $this->actingAs($medico);

        $response = $this->postJson($this->baseUrl('/inbox/channels'), [
            'type' => 'web',
            'name' => 'Site Médico',
        ]);

        $response->assertForbidden();
    }

    /** @test */
    public function test_web_channel_creation_records_audit(): void
    {
        $this->postJson($this->baseUrl('/inbox/channels'), [
            'type' => 'web',
            'name' => 'Site Principal',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'action' => 'channel.connected',
        ]);
    }
}
