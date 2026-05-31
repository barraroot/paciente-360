<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging\RateLimiting;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Events\MensagemRecebida;
use App\Domain\Messaging\Message\Models\Message;
use App\Events\Messaging\RateLimiting\ConversationCooldownStarted;
use App\Models\FunilColuna;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * **T207 (Fase 18 — Polish, FR-008a)** — camada PER-IDENTIFIER trips ANTES da
 * per-conversation quando seu cap é menor.
 *
 * Cenário: configuramos per_conversation alto (1000) e per_identifier baixo
 * (4) para isolar o limiter. Como `InboundConversationLimiter::checkOrThrow`
 * verifica per-conversation primeiro e per-identifier depois, ele só atinge
 * o per-identifier quando per-conversation está OK — perfeito pro teste.
 */
final class InboundCooldownPerIdentifierTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Channel $channel;

    private string $sharedIdentifier = '+5511955554444';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);

        FunilColuna::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Novos',
            'slug' => 'new',
            'posicao' => 1,
            'is_initial' => true,
            'is_terminal' => false,
            'motivo_obrigatorio' => false,
            'is_system' => true,
        ]);

        $this->channel = Channel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'whatsapp',
        ]);
    }

    public function test_per_identifier_limit_triggers_cooldown_with_correct_audit(): void
    {
        config([
            'messaging.rate.per_conversation' => 1000, // alto — não deve disparar
            'messaging.rate.per_identifier' => 4,
            'messaging.rate.window_minutes' => 10,
        ]);
        Event::fake([ConversationCooldownStarted::class]);

        $conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $this->channel->id,
            'external_thread_id' => $this->sharedIdentifier,
            'patient_id' => null,
        ]);

        // 4 mensagens — dentro do limite per-identifier (4).
        for ($i = 0; $i < 4; $i++) {
            $this->fireInbound($conversation, "msg {$i}");
        }
        $conversation->refresh();
        $this->assertNull($conversation->cooldown_until);

        // 5ª mensagem excede per-identifier.
        $this->fireInbound($conversation, 'msg 5 — excede ident');
        $conversation->refresh();

        $this->assertNotNull($conversation->cooldown_until);
        $this->assertEquals('rate_limit_per_identifier', $conversation->cooldown_reason);

        Event::assertDispatched(
            ConversationCooldownStarted::class,
            fn (ConversationCooldownStarted $e): bool => $e->limiterKey === 'per_identifier'
                && $e->conversation->id === $conversation->id,
        );
    }

    private function fireInbound(Conversation $conversation, string $body): void
    {
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'tenant_id' => $this->tenant->id,
            'direction' => 'in',
            'sender_type' => 'patient',
            'body' => $body,
            'sandbox' => false,
        ]);

        event(new MensagemRecebida($message, $conversation));
    }
}
