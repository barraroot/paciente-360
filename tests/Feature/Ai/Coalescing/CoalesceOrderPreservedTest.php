<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\Coalescing;

use App\Domain\Ai\Coalescing\Services\ConversationTurnCoordinator;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * **T080 (Fase 18 — US1, FR-007)** — ordem cronológica das mensagens
 * é preservada na fila do turno, independente da ordem de chegada.
 */
final class CoalesceOrderPreservedTest extends TestCase
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
    public function preserves_chronological_order_of_messages(): void
    {
        // Cria 5 mensagens com IDs 100-104 em sequência
        $messages = [];
        for ($i = 100; $i <= 104; $i++) {
            $msg = Message::factory()
                ->forTenant($this->tenant)
                ->for($this->conversation)
                ->create([
                    'direction' => 'in',
                    'sender_type' => 'patient',
                    'body' => "Message {$i}",
                ]);
            $messages[$i] = $msg;
        }

        // Dispara os eventos na ordem (100, 101, 102, 103, 104)
        foreach ($messages as $id => $msg) {
            $this->coordinator->joinOrStartTurn(
                conversationId: $this->conversation->id,
                messageId: $msg->id,
            );
        }

        // Verifica que pendingMessageIds retorna na ordem de entrada
        $pendingIds = $this->coordinator->pendingMessageIds($this->conversation->id);

        $this->assertSame(
            [$messages[100]->id, $messages[101]->id, $messages[102]->id, $messages[103]->id, $messages[104]->id],
            $pendingIds,
            'Mensagens devem estar em ordem cronológica de chegada'
        );
    }

    #[Test]
    public function preserves_order_even_with_unsorted_message_ids(): void
    {
        // Cria mensagens com IDs em ordem diferente
        $msgIds = [103, 100, 102, 104, 101];
        $msgMap = [];

        foreach ($msgIds as $id) {
            $msg = Message::factory()
                ->forTenant($this->tenant)
                ->for($this->conversation)
                ->create([
                    'direction' => 'in',
                    'sender_type' => 'patient',
                    'body' => "Message {$id}",
                ]);
            $msgMap[$id] = $msg->id;
        }

        // Chama joinOrStartTurn na ordem [103, 100, 102, 104, 101]
        // A ordem DEVE ser preservada, não reordenada numericamente
        foreach ($msgIds as $numericId) {
            $this->coordinator->joinOrStartTurn(
                conversationId: $this->conversation->id,
                messageId: $msgMap[$numericId],
            );
        }

        $pendingIds = $this->coordinator->pendingMessageIds($this->conversation->id);

        // Verifica que a ordem é EXATAMENTE a ordem de entrada (103, 100, 102, 104, 101)
        // conversão de DB IDs para ordem esperada
        $expectedOrder = array_map(fn ($id) => $msgMap[$id], $msgIds);

        $this->assertSame(
            $expectedOrder,
            $pendingIds,
            'Ordem deve ser preservada conforme chegada, não numericamente'
        );
    }

    #[Test]
    public function maintains_insertion_order_with_coordinator(): void
    {
        // Cria 3 mensagens
        $msg1 = Message::factory()
            ->forTenant($this->tenant)
            ->for($this->conversation)
            ->create(['direction' => 'in', 'sender_type' => 'patient']);

        $msg2 = Message::factory()
            ->forTenant($this->tenant)
            ->for($this->conversation)
            ->create(['direction' => 'in', 'sender_type' => 'patient']);

        $msg3 = Message::factory()
            ->forTenant($this->tenant)
            ->for($this->conversation)
            ->create(['direction' => 'in', 'sender_type' => 'patient']);

        // Simula o que o listener faria com cada mensagem
        $this->coordinator->joinOrStartTurn($this->conversation->id, $msg1->id);
        $this->coordinator->joinOrStartTurn($this->conversation->id, $msg2->id);
        $this->coordinator->joinOrStartTurn($this->conversation->id, $msg3->id);

        $pending = $this->coordinator->pendingMessageIds($this->conversation->id);

        $this->assertCount(3, $pending);
        $this->assertSame($msg1->id, $pending[0]);
        $this->assertSame($msg2->id, $pending[1]);
        $this->assertSame($msg3->id, $pending[2]);
    }
}
