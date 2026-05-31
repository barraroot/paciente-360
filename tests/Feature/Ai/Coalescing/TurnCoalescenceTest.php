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
 * **T074 (Fase 18 — US1, FR-001)** — happy path de coalescência híbrida:
 * 3 mensagens em sequência, cada uma incrementando versão e agendando flush.
 */
final class TurnCoalescenceTest extends TestCase
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
    public function three_inbound_messages_increment_version_and_schedule_flushes(): void
    {
        Bus::fake();

        $msg1 = Message::factory()
            ->forTenant($this->tenant)
            ->for($this->conversation)
            ->create(['direction' => 'in', 'sender_type' => 'patient', 'body' => 'First']);

        $msg2 = Message::factory()
            ->forTenant($this->tenant)
            ->for($this->conversation)
            ->create(['direction' => 'in', 'sender_type' => 'patient', 'body' => 'Second']);

        $msg3 = Message::factory()
            ->forTenant($this->tenant)
            ->for($this->conversation)
            ->create(['direction' => 'in', 'sender_type' => 'patient', 'body' => 'Third']);

        // Simula o que o listener faria ao receber cada mensagem
        $state1 = $this->coordinator->joinOrStartTurn($this->conversation->id, $msg1->id);
        $this->scheduler->scheduleFlush($this->conversation->id, $this->tenant->id, $state1->version);

        $state2 = $this->coordinator->joinOrStartTurn($this->conversation->id, $msg2->id);
        $this->scheduler->scheduleFlush($this->conversation->id, $this->tenant->id, $state2->version);

        $state3 = $this->coordinator->joinOrStartTurn($this->conversation->id, $msg3->id);
        $this->scheduler->scheduleFlush($this->conversation->id, $this->tenant->id, $state3->version);

        // Verifica estado do turno
        $this->assertSame(3, $this->coordinator->currentVersion($this->conversation->id));
        $this->assertSame(
            [$msg1->id, $msg2->id, $msg3->id],
            $this->coordinator->pendingMessageIds($this->conversation->id),
            'Mensagens devem estar na ordem de chegada'
        );
        $this->assertSame(0, $this->coordinator->reprocessCount($this->conversation->id));

        // Verifica que 3 FlushCoalescedTurnJob foram agendados (1 por mensagem)
        Bus::assertDispatched(FlushCoalescedTurnJob::class, 3);
    }

    #[Test]
    public function only_latest_flush_job_version_matches_current(): void
    {
        Bus::fake();

        $msg1 = Message::factory()
            ->forTenant($this->tenant)
            ->for($this->conversation)
            ->create(['direction' => 'in', 'sender_type' => 'patient']);

        $msg2 = Message::factory()
            ->forTenant($this->tenant)
            ->for($this->conversation)
            ->create(['direction' => 'in', 'sender_type' => 'patient']);

        // Simula envio de 2 mensagens
        $state1 = $this->coordinator->joinOrStartTurn($this->conversation->id, $msg1->id);
        $this->scheduler->scheduleFlush($this->conversation->id, $this->tenant->id, $state1->version);

        $state2 = $this->coordinator->joinOrStartTurn($this->conversation->id, $msg2->id);
        $this->scheduler->scheduleFlush($this->conversation->id, $this->tenant->id, $state2->version);

        // Verifica que temos 2 versões
        $this->assertSame(1, $state1->version);
        $this->assertSame(2, $state2->version);
        $this->assertSame(2, $this->coordinator->currentVersion($this->conversation->id));

        // Verifica que 2 FlushCoalescedTurnJob foram agendados com versões corretas
        Bus::assertDispatched(FlushCoalescedTurnJob::class, function ($job) {
            return $job->turnVersion === 1;
        });

        Bus::assertDispatched(FlushCoalescedTurnJob::class, function ($job) {
            return $job->turnVersion === 2;
        });
    }
}
