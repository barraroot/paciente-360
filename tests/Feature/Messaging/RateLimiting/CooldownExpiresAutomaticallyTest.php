<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging\RateLimiting;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\RateLimiting\IsConversationOnCooldownChecker;
use App\Events\Messaging\RateLimiting\ConversationCooldownEnded;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * **T207 (Fase 18 — Polish, FR-008b)** — cooldown expira automaticamente
 * (lazy) na primeira leitura após `cooldown_until` passar — não exige cron.
 */
final class CooldownExpiresAutomaticallyTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_clears_expired_cooldown_and_dispatches_expired_event(): void
    {
        Event::fake([ConversationCooldownEnded::class]);

        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => 'whatsapp',
        ]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'cooldown_until' => Carbon::now()->subMinutes(1), // já passou
            'cooldown_reason' => 'rate_limit_per_conversation',
        ]);

        $checker = app(IsConversationOnCooldownChecker::class);

        $isOn = $checker->check($conversation);

        $this->assertFalse($isOn, 'check() deve retornar false quando a janela já passou.');

        $conversation->refresh();
        $this->assertNull($conversation->cooldown_until);
        $this->assertNull($conversation->cooldown_reason);

        Event::assertDispatched(
            ConversationCooldownEnded::class,
            fn (ConversationCooldownEnded $e): bool => $e->endedBy === 'expired'
                && $e->actorUserId === null,
        );
    }

    public function test_check_returns_true_when_still_active_and_does_not_clear(): void
    {
        Event::fake([ConversationCooldownEnded::class]);

        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => 'whatsapp',
        ]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'cooldown_until' => Carbon::now()->addMinutes(10),
            'cooldown_reason' => 'rate_limit_per_conversation',
        ]);

        $checker = app(IsConversationOnCooldownChecker::class);

        $this->assertTrue($checker->check($conversation));

        $conversation->refresh();
        $this->assertNotNull($conversation->cooldown_until);
        Event::assertNotDispatched(ConversationCooldownEnded::class);
    }

    public function test_check_returns_false_when_no_cooldown_set(): void
    {
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => 'whatsapp',
        ]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'cooldown_until' => null,
        ]);

        $this->assertFalse(app(IsConversationOnCooldownChecker::class)->check($conversation));
    }
}
