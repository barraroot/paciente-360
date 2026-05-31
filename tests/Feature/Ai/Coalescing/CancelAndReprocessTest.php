<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\Coalescing;

use App\Domain\Ai\Coalescing\Services\ConversationTurnCoordinator;
use App\Domain\Ai\Coalescing\Services\PassiveDebounceScheduler;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Jobs\Ai\FlushCoalescedTurnJob;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * **T075 (Fase 18 — US1, FR-002 + FR-004)** — cancel-and-reprocess:
 * mensagem nova chega durante o processamento, causando re-agendamento do flush.
 * Testa até o cap (3 reprocessos).
 */
final class CancelAndReprocessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Conversation $conversation;

    private ConversationTurnCoordinator $coordinator;

    private PassiveDebounceScheduler $scheduler;

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
        $this->scheduler = app(PassiveDebounceScheduler::class);
    }

    protected function tearDown(): void
    {
        Redis::flushdb();
        parent::tearDown();
    }

    #[Test]
    public function reprocess_when_message_arrives_during_processing(): void
    {
        Bus::fake();

        // Primeira mensagem entra no turno (version=1)
        $msg1 = Message::factory()
            ->forTenant($this->tenant)
            ->for($this->conversation)
            ->create(['direction' => 'in', 'sender_type' => 'patient']);

        $state1 = $this->coordinator->joinOrStartTurn($this->conversation->id, $msg1->id);
        $this->scheduler->scheduleFlush($this->conversation->id, $this->tenant->id, $state1->version);

        $this->assertSame(1, $this->coordinator->currentVersion($this->conversation->id));

        // Segunda mensagem chega durante o processamento (version=2)
        Bus::fake();
        $msg2 = Message::factory()
            ->forTenant($this->tenant)
            ->for($this->conversation)
            ->create(['direction' => 'in', 'sender_type' => 'patient']);

        $state2 = $this->coordinator->joinOrStartTurn($this->conversation->id, $msg2->id);
        $this->assertSame(2, $state2->version);

        // Incrementa reprocess e re-agenda (como faria ProcessAiResponseJob::handleSuperseded)
        $this->coordinator->incrementReprocess($this->conversation->id);
        $this->scheduler->scheduleFlush($this->conversation->id, $this->tenant->id, $state2->version);

        // Verifica que reprocessCount foi incrementado
        $this->assertSame(1, $this->coordinator->reprocessCount($this->conversation->id));

        // Verifica que novo FlushCoalescedTurnJob foi agendado
        Bus::assertDispatched(FlushCoalescedTurnJob::class, function ($job) {
            return $job->turnVersion === 2;
        });
    }

    #[Test]
    public function respects_max_reprocess_cap(): void
    {
        // Primeira mensagem
        $msg1 = Message::factory()
            ->forTenant($this->tenant)
            ->for($this->conversation)
            ->create(['direction' => 'in', 'sender_type' => 'patient']);

        $state1 = $this->coordinator->joinOrStartTurn($this->conversation->id, $msg1->id);
        $this->assertSame(1, $state1->version);

        // Incrementa reprocess 3 vezes (até o cap)
        // Cada incremento representa uma nova mensagem que chegou durante processamento
        for ($i = 1; $i <= 3; $i++) {
            $newMsg = Message::factory()
                ->forTenant($this->tenant)
                ->for($this->conversation)
                ->create(['direction' => 'in', 'sender_type' => 'patient']);

            $newState = $this->coordinator->joinOrStartTurn($this->conversation->id, $newMsg->id);
            $this->coordinator->incrementReprocess($this->conversation->id);
        }

        $reprocessCount = $this->coordinator->reprocessCount($this->conversation->id);
        $currentVersion = $this->coordinator->currentVersion($this->conversation->id);

        // Verificar que reprocessCount atingiu 3 (cap)
        $this->assertSame(3, $reprocessCount);

        // Verifica que temos 4 mensagens (versão 4)
        $this->assertSame(4, $currentVersion);

        // Cap foi atingido
        $this->assertTrue(
            $reprocessCount >= (int) config('ai.matricial.coalesce.max_reprocesses', 3)
        );
    }
}
