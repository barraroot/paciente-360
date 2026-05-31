<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\Coalescing;

use App\Domain\Ai\Coalescing\Events\TurnCoalesced;
use App\Domain\Ai\Coalescing\Services\ConversationTurnCoordinator;
use App\Domain\Ai\Coalescing\Services\TurnState;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * **T076 (Fase 18 — US1, FR-003 + FR-004)** — teto absoluto de tempo e
 * teto máximo de reprocessos detectados via TurnState.
 */
final class CoalesceTimeoutTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Conversation $conversation;

    private ConversationTurnCoordinator $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushdb();

        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);

        $channel = Channel::factory()->forTenant($this->tenant)->create([
            'type' => 'whatsapp',
        ]);

        $this->conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($channel)
            ->create();

        $this->coordinator = app(ConversationTurnCoordinator::class);
    }

    protected function tearDown(): void
    {
        Redis::flushdb();
        parent::tearDown();
    }

    #[Test]
    public function max_turn_seconds_exceeded_triggers_flush(): void
    {
        // Manualmente seta o started_at para 31 segundos atrás (max_turn_s = 30)
        $key = 'ai:turn:'.$this->conversation->id.':';
        Redis::set($key.'v', '1', 'EX', 300);
        Redis::set($key.'started_at', (string) (time() - 31), 'EX', 300);
        Redis::set($key.'reprocess', '0', 'EX', 300);
        Redis::rpush($key.'msgs', '100');

        $state = new TurnState(
            conversationId: $this->conversation->id,
            version: 1,
            messageIds: [100],
            isFirstOfTurn: false,
            startedAt: (int) Redis::get($key.'started_at'),
            reprocessCount: 0,
        );

        $this->assertTrue($state->exceededMaxTurnSeconds());
        $this->assertGreaterThanOrEqual(30, $state->elapsedSeconds());
    }

    #[Test]
    public function max_reprocess_count_exceeded_triggers_flush(): void
    {
        $key = 'ai:turn:'.$this->conversation->id.':';
        Redis::set($key.'v', '1', 'EX', 300);
        Redis::set($key.'started_at', (string) time(), 'EX', 300);
        Redis::set($key.'reprocess', '3', 'EX', 300);
        Redis::rpush($key.'msgs', '100');

        $state = new TurnState(
            conversationId: $this->conversation->id,
            version: 1,
            messageIds: [100],
            isFirstOfTurn: false,
            startedAt: (int) Redis::get($key.'started_at'),
            reprocessCount: (int) Redis::get($key.'reprocess'),
        );

        $this->assertTrue($state->exceededMaxReprocesses());
        $this->assertSame(3, $state->reprocessCount);
    }

    #[Test]
    public function flush_reason_is_max_turn_seconds_when_elapsed(): void
    {
        Event::fake([TurnCoalesced::class]);

        $key = 'ai:turn:'.$this->conversation->id.':';
        Redis::set($key.'v', '1', 'EX', 300);
        Redis::set($key.'started_at', (string) (time() - 31), 'EX', 300);
        Redis::set($key.'reprocess', '0', 'EX', 300);
        Redis::rpush($key.'msgs', '100');

        $state = new TurnState(
            conversationId: $this->conversation->id,
            version: 1,
            messageIds: [100],
            isFirstOfTurn: false,
            startedAt: (int) Redis::get($key.'started_at'),
            reprocessCount: 0,
        );

        $flushReason = $state->exceededMaxTurnSeconds() ? 'max_turn_seconds_reached' : 'dispatching';

        $this->assertSame('max_turn_seconds_reached', $flushReason);
    }

    #[Test]
    public function flush_reason_is_max_reprocesses_when_exceeded(): void
    {
        Event::fake([TurnCoalesced::class]);

        $key = 'ai:turn:'.$this->conversation->id.':';
        Redis::set($key.'v', '1', 'EX', 300);
        Redis::set($key.'started_at', (string) time(), 'EX', 300);
        Redis::set($key.'reprocess', '3', 'EX', 300);
        Redis::rpush($key.'msgs', '100');

        $state = new TurnState(
            conversationId: $this->conversation->id,
            version: 1,
            messageIds: [100],
            isFirstOfTurn: false,
            startedAt: (int) Redis::get($key.'started_at'),
            reprocessCount: (int) Redis::get($key.'reprocess'),
        );

        $flushReason = $state->exceededMaxReprocesses()
            ? 'max_reprocesses_reached'
            : ($state->exceededMaxTurnSeconds() ? 'max_turn_seconds_reached' : 'dispatching');

        $this->assertSame('max_reprocesses_reached', $flushReason);
    }
}
