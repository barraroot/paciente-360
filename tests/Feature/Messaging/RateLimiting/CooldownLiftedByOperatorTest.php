<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging\RateLimiting;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Events\Messaging\RateLimiting\ConversationCooldownEnded;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T207 (Fase 18 — Polish, FR-008b)** — operador encerra cooldown manualmente.
 *
 * Endpoint: POST /api/v1/inbox/conversations/{id}/cooldown/end
 * Permission: messaging.cooldown.manage
 */
final class CooldownLiftedByOperatorTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->user] = $this->tenantAndUserForRole('cooldown-op', 'admin-clinica');
        $this->user->givePermissionTo('messaging.cooldown.manage');
    }

    public function test_operator_can_end_cooldown_via_api(): void
    {
        Event::fake([ConversationCooldownEnded::class]);

        $channel = Channel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'whatsapp',
        ]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $channel->id,
            'cooldown_until' => Carbon::now()->addMinutes(15),
            'cooldown_reason' => 'rate_limit_per_conversation',
        ]);

        $response = $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->postJson("/api/v1/inbox/conversations/{$conversation->id}/cooldown/end");

        $response->assertOk();
        $response->assertJsonPath('data.is_on_cooldown', false);
        $response->assertJsonPath('data.cooldown_until', null);
        $response->assertJsonPath('data.cooldown_reason', null);

        $conversation->refresh();
        $this->assertNull($conversation->cooldown_until);
        $this->assertNull($conversation->cooldown_reason);

        Event::assertDispatched(
            ConversationCooldownEnded::class,
            fn (ConversationCooldownEnded $e): bool => $e->endedBy === 'operator'
                && $e->actorUserId === $this->user->id,
        );
    }

    public function test_end_cooldown_is_idempotent_when_not_on_cooldown(): void
    {
        Event::fake([ConversationCooldownEnded::class]);

        $channel = Channel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'whatsapp',
        ]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $channel->id,
            'cooldown_until' => null,
        ]);

        $response = $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->postJson("/api/v1/inbox/conversations/{$conversation->id}/cooldown/end");

        $response->assertOk();
        // Nenhum evento — idempotente, sem efeito.
        Event::assertNotDispatched(ConversationCooldownEnded::class);
    }

    public function test_cross_tenant_cooldown_end_returns_404(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherChannel = Channel::factory()->create([
            'tenant_id' => $otherTenant->id,
            'type' => 'whatsapp',
        ]);
        $otherConversation = Conversation::factory()->create([
            'tenant_id' => $otherTenant->id,
            'channel_id' => $otherChannel->id,
            'cooldown_until' => Carbon::now()->addMinutes(10),
        ]);

        $response = $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->postJson("/api/v1/inbox/conversations/{$otherConversation->id}/cooldown/end");

        // Global scope filtra por tenant — route model binding deve 404.
        $response->assertNotFound();
    }
}
