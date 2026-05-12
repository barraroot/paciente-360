<?php

namespace Tests\Feature\Fase3\US1_WhatsApp;

use App\Domain\Messaging\Channel\Events\CanalDesconectado;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T064 — Desconexão de canal (DELETE /api/v1/inbox/channels/{id}).
 *
 * Cobre AC-4.1.10 (soft delete + evento + validação de conversas ativas).
 *
 * TDD RED: delete endpoint ainda não existe.
 */
class DisconnectChannelTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $adminUser;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->adminUser] = $this->tenantAndUserForRole('clinica-disconnect', 'admin-clinica');

        $this->channel = Channel::factory()
            ->whatsapp()
            ->forTenant($this->tenant)
            ->create(['status' => 'ativo']);
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    /** @test */
    public function test_admin_can_disconnect_channel_soft_deletes_and_fires_event(): void
    {
        Event::fake([CanalDesconectado::class]);

        $response = $this->deleteJson($this->baseUrl("/inbox/channels/{$this->channel->id}"));

        $response->assertNoContent();

        // Verifica soft delete
        $this->channel->refresh();
        $this->assertNotNull($this->channel->deleted_at);
        $this->assertEquals('desconectado', $this->channel->status);

        // Verifica evento
        Event::assertDispatched(CanalDesconectado::class);

        // Verifica audit log
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'action' => 'channel.disconnected',
        ]);
    }

    /** @test */
    public function test_disconnecting_channel_with_active_conversations_returns_409_unless_forced(): void
    {
        // Cria uma conversa aberta
        Conversation::factory()
            ->aberta()
            ->forTenant($this->tenant)
            ->create([
                'channel_id' => $this->channel->id,
            ]);

        // Tenta desconectar sem force
        $response = $this->deleteJson($this->baseUrl("/inbox/channels/{$this->channel->id}"));

        $response->assertConflict();

        // Verifica que não foi deletado
        $this->assertNull($this->channel->refresh()->deleted_at);

        // Com force=true, deve passar
        $response2 = $this->deleteJson($this->baseUrl("/inbox/channels/{$this->channel->id}?force=true"));

        $response2->assertNoContent();

        // Verifica que foi deletado
        $this->assertNotNull($this->channel->refresh()->deleted_at);
    }

    /** @test */
    public function test_user_without_disconnect_ability_returns_403(): void
    {
        // Cria um médico sem ability channel.disconnect
        $doctor = $this->createUserForTenant($this->tenant);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->tenant->id);
        $registrar->forgetCachedPermissions();
        $doctor->assignRole('medico');

        $this->actingAs($doctor);

        $response = $this->deleteJson($this->baseUrl("/inbox/channels/{$this->channel->id}"));

        $response->assertForbidden();

        // Verifica que não foi deletado
        $this->assertNull($this->channel->refresh()->deleted_at);
    }
}
