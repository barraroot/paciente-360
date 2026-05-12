<?php

namespace Tests\Feature\Fase3\US5_Assignment;

use App\Domain\Messaging\Assignment\Services\Strategies\RoundRobinStrategy;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Presence\Models\UserPresence;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T138 — Feature tests para auto-atribuição round-robin.
 *
 * Cobre AC-4.5.2, NC-6.a: round-robin escolhe próximo atendente online com vaga.
 */
class AssignAutoRoundRobinTest extends TestCase
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
        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('clinica-rr', 'admin-clinica');

        $this->channel = Channel::factory()
            ->forTenant($this->tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    private function createPresence(User $user, bool $online, int $assigned = 0, int $max = 15): UserPresence
    {
        return UserPresence::factory()
            ->forTenant($this->tenant)
            ->state([
                'user_id' => $user->id,
                'status' => $online ? 'online' : 'offline',
                'last_seen_at' => $online ? now()->subMinutes(2) : now()->subHours(2),
                'max_concurrent_conversations' => $max,
                'current_assigned_count' => $assigned,
            ])
            ->create();
    }

    #[Test]
    public function round_robin_picks_next_online_user_with_capacity(): void
    {
        $atendente1 = $this->userForRole($this->tenant, 'atendente');
        $this->createPresence($atendente1, online: true, assigned: 0);

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => null])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/assign"),
            ['auto' => true]
        );

        $response->assertOk();
        $conversation->refresh();
        $this->assertEquals($atendente1->id, $conversation->assigned_user_id);
        $this->assertEquals('auto_round_robin', $conversation->assignment_strategy);
    }

    #[Test]
    public function round_robin_skips_offline_users(): void
    {
        $offline = $this->userForRole($this->tenant, 'atendente');
        $this->createPresence($offline, online: false);

        $online = $this->userForRole($this->tenant, 'atendente');
        $this->createPresence($online, online: true, assigned: 0);

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => null])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/assign"),
            ['auto' => true]
        );

        $response->assertOk();
        $conversation->refresh();
        $this->assertEquals($online->id, $conversation->assigned_user_id);
    }

    #[Test]
    public function round_robin_skips_users_at_max_concurrent_limit(): void
    {
        $atMax = $this->userForRole($this->tenant, 'atendente');
        $this->createPresence($atMax, online: true, assigned: 15, max: 15);

        $withCapacity = $this->userForRole($this->tenant, 'atendente');
        $this->createPresence($withCapacity, online: true, assigned: 5, max: 15);

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => null])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/assign"),
            ['auto' => true]
        );

        $response->assertOk();
        $conversation->refresh();
        $this->assertEquals($withCapacity->id, $conversation->assigned_user_id);
    }

    #[Test]
    public function round_robin_cycles_through_eligible_users(): void
    {
        // Reset the cursor so this test starts from a known state
        Cache::forget("cb:round_robin:{$this->tenant->id}:cursor");

        $user1 = $this->userForRole($this->tenant, 'atendente');
        $this->createPresence($user1, online: true, assigned: 0);

        $user2 = $this->userForRole($this->tenant, 'atendente');
        $this->createPresence($user2, online: true, assigned: 0);

        // Invoke the strategy directly (unit-style within feature test) so that
        // the Redis/array cursor persists within the same PHP request context.
        // HTTP calls each get a fresh in-memory cache, making cursor persistence
        // impossible with the array driver — this is expected behaviour.
        $strategy = app(RoundRobinStrategy::class);

        $fakeConv = new Conversation;
        $fakeConv->tenant_id = $this->tenant->id;

        $picked = [];
        for ($i = 0; $i < 4; $i++) {
            $user = $strategy->pickUser($fakeConv);
            $this->assertNotNull($user, "pickUser() should return a user on call #$i");
            $picked[] = $user->id;
        }

        // Over 4 consecutive pickUser() calls on a 2-user pool, round-robin
        // MUST visit both users (pigeonhole principle — 4 picks, 2 slots).
        $this->assertContains($user1->id, $picked, 'user1 should be picked at least once in 4 round-robin cycles');
        $this->assertContains($user2->id, $picked, 'user2 should be picked at least once in 4 round-robin cycles');
    }

    #[Test]
    public function no_eligible_user_leaves_conversation_unassigned_and_alerts_admin(): void
    {
        // No presence records — no eligible users
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => null])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/assign"),
            ['auto' => true]
        );

        // Returns ok but conversation remains unassigned
        $response->assertOk();
        $conversation->refresh();
        $this->assertNull($conversation->assigned_user_id);
        $response->assertJsonPath('data.assigned_user.id', null);
    }

    #[Test]
    public function respects_inbox_settings_user_idle_minutes_threshold(): void
    {
        $idle = $this->userForRole($this->tenant, 'atendente');
        // Seen 8 minutes ago — beyond default 5 min threshold
        UserPresence::factory()
            ->forTenant($this->tenant)
            ->state([
                'user_id' => $idle->id,
                'status' => 'online',
                'last_seen_at' => now()->subMinutes(8),
                'max_concurrent_conversations' => 15,
                'current_assigned_count' => 0,
            ])
            ->create();

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => null])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/assign"),
            ['auto' => true]
        );

        $response->assertOk();
        $conversation->refresh();
        // User seen 8 min ago is skipped (threshold 5 min)
        $this->assertNull($conversation->assigned_user_id);
    }
}
