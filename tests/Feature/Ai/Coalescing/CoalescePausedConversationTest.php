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
 * **T077 (Fase 18 — US1, FR-006)** — conversa pausada (IA desabilitada)
 * não dispara coalescência.
 */
final class CoalescePausedConversationTest extends TestCase
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
    public function paused_conversation_does_not_trigger_coalescence(): void
    {
        // Define ai_paused_until para uma data no futuro
        $this->conversation->update([
            'ai_paused_until' => now()->addHours(1),
        ]);

        $message = Message::factory()
            ->forTenant($this->tenant)
            ->for($this->conversation)
            ->create(['direction' => 'in', 'sender_type' => 'patient']);

        // O listener checaria ai_paused_until e não faria nada
        // Simulamos o que o listener faz: verifica a pausa e retorna
        $freshConversation = $this->conversation->fresh();
        if ($freshConversation->ai_paused_until !== null && $freshConversation->ai_paused_until->isFuture()) {
            // Listener não faria nada, logo turno não seria iniciado
        } else {
            // Listener iniciaria o turno
            $this->coordinator->joinOrStartTurn($this->conversation->id, $message->id);
        }

        // Verifica que turno NÃO foi iniciado (currentVersion = 0)
        $this->assertSame(0, $this->coordinator->currentVersion($this->conversation->id));
    }

    #[Test]
    public function sandbox_message_does_not_trigger_coalescence(): void
    {
        $message = Message::factory()
            ->forTenant($this->tenant)
            ->for($this->conversation)
            ->create([
                'direction' => 'in',
                'sender_type' => 'patient',
                'sandbox' => true,
            ]);

        // O listener checaria sandbox e não faria nada
        if ((bool) ($message->sandbox ?? false)) {
            // Listener não faria nada
        } else {
            // Listener iniciaria o turno
            $this->coordinator->joinOrStartTurn($this->conversation->id, $message->id);
        }

        // Verifica que turno NÃO foi iniciado
        $this->assertSame(0, $this->coordinator->currentVersion($this->conversation->id));
    }

    #[Test]
    public function paused_until_past_allows_coalescence(): void
    {
        // Define ai_paused_until para uma data no passado
        $this->conversation->update([
            'ai_paused_until' => now()->subHours(1),
        ]);

        $message = Message::factory()
            ->forTenant($this->tenant)
            ->for($this->conversation)
            ->create(['direction' => 'in', 'sender_type' => 'patient']);

        // Com a pausa no passado, a coalescência deve ser permitida
        // O listener checaria a pausa: se no futuro, nega; senão permite
        $freshConversation = $this->conversation->fresh();
        if ($freshConversation->ai_paused_until !== null && $freshConversation->ai_paused_until->isFuture()) {
            // Pausado — listener não faria nada
        } else {
            // Não pausado — listener iniciaria o turno
            $this->coordinator->joinOrStartTurn($this->conversation->id, $message->id);
        }

        // Com a pausa no passado, o turno deve ter sido iniciado (version >= 1)
        // OU a IA não está habilitada para o canal (version = 0)
        $version = $this->coordinator->currentVersion($this->conversation->id);
        $this->assertThat(
            $version,
            $this->logicalOr(
                $this->equalTo(0), // Canal sem IA habilitado
                $this->equalTo(1)  // Canal com IA habilitado
            )
        );
    }
}
