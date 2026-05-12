<?php

namespace Tests\Feature\Fase3\US5_Assignment;

use App\Domain\Messaging\Assignment\Events\ConversaAtribuida;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Conversation\Models\ConversationAssignment;
use App\Domain\Messaging\Presence\Models\UserPresence;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T137 — Feature tests para POST /inbox/conversations/{id}/assign (manual).
 *
 * Cobre AC-4.5.1 (atribuição manual), AC-4.5.8 (limite max_concurrent).
 */
class AssignManualTest extends TestCase
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
        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('clinica-assign', 'admin-clinica');

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
    public function admin_can_manually_assign_conversation_to_attendant_in_same_tenant(): void
    {
        $atendente = $this->userForRole($this->tenant, 'atendente');

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => null])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/assign"),
            ['user_id' => $atendente->id]
        );

        $response->assertOk();
        $response->assertJsonPath('data.assigned_user.id', $atendente->id);

        $conversation->refresh();
        $this->assertEquals($atendente->id, $conversation->assigned_user_id);
        $this->assertEquals('manual', $conversation->assignment_strategy);
    }

    #[Test]
    public function attendant_can_pick_unassigned_conversation_for_themselves(): void
    {
        $atendente = $this->userForRole($this->tenant, 'atendente');
        $this->actingAs($atendente);
        $this->app->instance('tenant', $this->tenant);

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => null])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/assign"),
            ['user_id' => $atendente->id]
        );

        $response->assertOk();
        $conversation->refresh();
        $this->assertEquals($atendente->id, $conversation->assigned_user_id);
    }

    #[Test]
    public function assignment_creates_history_row_with_user_id_and_reason(): void
    {
        $atendente = $this->userForRole($this->tenant, 'atendente');

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => null])
            ->create();

        $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/assign"),
            ['user_id' => $atendente->id]
        )->assertOk();

        $assignment = ConversationAssignment::where('conversation_id', $conversation->id)
            ->where('user_id', $atendente->id)
            ->first();

        $this->assertNotNull($assignment);
        $this->assertEquals('manual', $assignment->reason);
        $this->assertEquals($this->admin->id, $assignment->assigned_by);
    }

    #[Test]
    public function fires_conversa_atribuida_event_with_manual_mode(): void
    {
        Event::fake();

        $atendente = $this->userForRole($this->tenant, 'atendente');

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => null])
            ->create();

        $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/assign"),
            ['user_id' => $atendente->id]
        )->assertOk();

        Event::assertDispatched(
            ConversaAtribuida::class,
            function ($event) use ($atendente): bool {
                return $event->mode === 'manual'
                    && $event->conversation->assigned_user_id === $atendente->id;
            }
        );
    }

    #[Test]
    public function rejects_assign_to_user_above_max_concurrent_limit(): void
    {
        $atendente = $this->userForRole($this->tenant, 'atendente');

        // Presence at max limit
        UserPresence::factory()
            ->forTenant($this->tenant)
            ->state([
                'user_id' => $atendente->id,
                'status' => 'online',
                'last_seen_at' => now()->subMinutes(1),
                'max_concurrent_conversations' => 5,
                'current_assigned_count' => 5,
            ])
            ->create();

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => null])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/assign"),
            ['user_id' => $atendente->id]
        );

        $response->assertUnprocessable();
        $response->assertJsonPath('error', 'user_at_max_limit');
    }

    #[Test]
    public function requires_inbox_assign_ability(): void
    {
        $financeiro = $this->userForRole($this->tenant, 'financeiro');
        $this->actingAs($financeiro);
        $this->app->instance('tenant', $this->tenant);

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->create();

        $atendente = $this->userForRole($this->tenant, 'atendente');

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/assign"),
            ['user_id' => $atendente->id]
        );

        $response->assertForbidden();
    }

    #[Test]
    public function cross_tenant_user_target_returns_403_or_404(): void
    {
        // Tenant B
        $tenantB = $this->bootstrapTenantWithRoles('clinica-beta-assign');
        $atendenteB = $this->userForRole($tenantB, 'atendente');

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->create();

        // Admin A trying to assign to attendant from Tenant B
        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/assign"),
            ['user_id' => $atendenteB->id]
        );

        $response->assertStatus(403);
    }
}
