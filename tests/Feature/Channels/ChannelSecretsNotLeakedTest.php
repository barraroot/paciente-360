<?php

namespace Tests\Feature\Channels;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * Gate G6 (US2) — segredos de sessão/credenciais nunca aparecem no ChannelResource.
 */
class ChannelSecretsNotLeakedTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('clinica-secrets', 'admin-clinica');
    }

    public function test_evolution_instance_token_is_not_exposed_in_resource(): void
    {
        $channel = Channel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'whatsapp',
            'provider' => 'evolution',
            'status' => 'ativo',
            'provider_metadata' => [
                'instance_name' => 'tenant_x',
                'instance_token' => 'SUPER-SECRET-TOKEN',
                'connected_number' => '+5511999999999',
            ],
        ]);

        $response = $this->getJson($this->tenantUrl($this->tenant, '/inbox/channels'));
        $response->assertOk();

        $raw = $response->getContent();
        $this->assertStringNotContainsString('SUPER-SECRET-TOKEN', $raw);
        $this->assertStringNotContainsString('instance_token', $raw);

        // O número conectado (não-secreto) pode ser exposto.
        $this->assertStringContainsString('+5511999999999', $raw);
        unset($channel);
    }
}
