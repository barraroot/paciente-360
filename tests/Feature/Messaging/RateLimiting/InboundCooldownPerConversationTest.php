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
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * **T207 (Fase 18 — Polish, FR-008a/b)** — camada PER-CONVERSATION (30 msgs / 10min)
 * dispara cooldown auditável.
 */
final class InboundCooldownPerConversationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Channel $channel;

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

    protected function tearDown(): void
    {
        RateLimiter::clear("conv:{$this->tenant->id}:*");
        parent::tearDown();
    }

    public function test_per_conversation_limit_triggers_cooldown_with_audit_event(): void
    {
        config(['messaging.rate.per_conversation' => 5, 'messaging.rate.window_minutes' => 10]);
        Event::fake([ConversationCooldownStarted::class]);

        $conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $this->channel->id,
            'external_thread_id' => '+5511988887777',
            'patient_id' => null,
        ]);

        // Dispara 5 mensagens (dentro do limite — não deve abrir cooldown).
        for ($i = 0; $i < 5; $i++) {
            $this->fireInbound($conversation, "msg {$i}");
        }
        $conversation->refresh();
        $this->assertNull($conversation->cooldown_until, 'Não deve abrir cooldown dentro do limite.');

        // 6ª mensagem excede — deve abrir cooldown.
        $this->fireInbound($conversation, 'msg 6 — excede');
        $conversation->refresh();

        $this->assertNotNull($conversation->cooldown_until, 'cooldown_until deve estar populado após exceder limite.');
        $this->assertTrue(
            $conversation->cooldown_until->isFuture(),
            'cooldown_until deve estar no futuro.',
        );
        $this->assertEquals('rate_limit_per_conversation', $conversation->cooldown_reason);
        $this->assertEquals('alta', $conversation->priority);

        Event::assertDispatched(
            ConversationCooldownStarted::class,
            fn (ConversationCooldownStarted $e): bool => $e->limiterKey === 'per_conversation'
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
