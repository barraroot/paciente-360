<?php

namespace Tests\Feature\Fase3\US5_Assignment;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T142 — Feature tests para bloqueio de cross-tenant em assign/transfer.
 *
 * Princípio II não-negociável: cross-tenant retorna 403 + gera audit log.
 */
class CrossTenantTransferBlockedTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('clinica-xt', 'admin-clinica');

        $this->channel = Channel::factory()
            ->forTenant($this->tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    #[Test]
    public function transfer_to_user_of_different_tenant_returns_403(): void
    {
        $tenantB = $this->bootstrapTenantWithRoles('clinica-xt-beta');
        $atendenteB = $this->userForRole($tenantB, 'atendente');

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => $this->admin->id])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/transfer"),
            [
                'user_id' => $atendenteB->id,
                'transfer_note' => 'Tentando transferir para outro tenant.',
            ]
        );

        $response->assertStatus(403);
    }

    #[Test]
    public function transfer_to_user_of_different_tenant_creates_audit_entry(): void
    {
        $tenantB = $this->bootstrapTenantWithRoles('clinica-xt-beta-audit');
        $atendenteB = $this->userForRole($tenantB, 'atendente');

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => $this->admin->id])
            ->create();

        $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/transfer"),
            [
                'user_id' => $atendenteB->id,
                'transfer_note' => 'Tentando cross-tenant transfer.',
            ]
        );

        // Audit log should record the attempt
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'conversa.transferencia.cross_tenant_bloqueado',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[Test]
    public function assign_to_user_of_different_tenant_returns_403(): void
    {
        $tenantB = $this->bootstrapTenantWithRoles('clinica-xt-assign-beta');
        $atendenteB = $this->userForRole($tenantB, 'atendente');

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => null])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/assign"),
            ['user_id' => $atendenteB->id]
        );

        $response->assertStatus(403);
    }

    #[Test]
    public function policy_validates_target_user_belongs_to_authenticated_tenant(): void
    {
        $tenantB = $this->bootstrapTenantWithRoles('clinica-xt-policy-beta');
        $atendenteB = $this->userForRole($tenantB, 'atendente');

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => null])
            ->create();

        // Try assign with cross-tenant user
        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/assign"),
            ['user_id' => $atendenteB->id]
        );

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'cross_tenant_blocked');
    }
}
