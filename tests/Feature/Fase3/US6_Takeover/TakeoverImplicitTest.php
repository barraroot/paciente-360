<?php

namespace Tests\Feature\Fase3\US6_Takeover;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Events\ConversaAssumidaPorHumano;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Events\MensagemEnviada;
use App\Domain\Messaging\Message\Events\MensagemRecebida;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T164 — Feature tests para Takeover Implícito (AC-4.6.2).
 *
 * Enviar mensagem outbound via MessagesController dispara listener que
 * seta ai_paused_until automaticamente via SetAiPausedOnOutboundMessageListener.
 */
class TakeoverImplicitTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $attendant;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->attendant] = $this->tenantAndUserForRole('clinica-takeover-implicit', 'atendente');

        $this->channel = Channel::factory()
            ->forTenant($this->tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    private function makeConversation(): Conversation
    {
        return Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->aberta()
            ->comJanela24hAberta()
            ->create(['ai_paused_until' => null, 'ai_pause_set_by' => null]);
    }

    #[Test]
    public function sending_outbound_message_sets_ai_paused_until_automatically_via_listener(): void
    {
        $conversation = $this->makeConversation();

        $this->assertNull($conversation->ai_paused_until);

        // Send outbound message — this triggers the listener
        $this->withHeaders(['Idempotency-Key' => 'test-implicit-'.$conversation->id])
            ->postJson(
                $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
                [
                    'content_type' => 'text',
                    'body' => 'Olá, posso ajudar?',
                ]
            )->assertStatus(201);

        $conversation->refresh();
        $this->assertNotNull($conversation->ai_paused_until);
        $this->assertTrue($conversation->ai_paused_until->isFuture());
    }

    #[Test]
    public function implicit_takeover_uses_tenant_setting_ai_pause_minutes(): void
    {
        // Set tenant-level ai_pause_minutes via settings JSONB (T181)
        $this->tenant->settings = ['inbox' => ['ai_pause_minutes' => 60]];
        $this->tenant->save();

        $conversation = $this->makeConversation();

        $this->withHeaders(['Idempotency-Key' => 'test-custom-pause-'.$conversation->id])
            ->postJson(
                $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
                [
                    'content_type' => 'text',
                    'body' => 'Mensagem com pausa custom.',
                ]
            )->assertStatus(201);

        $conversation->refresh();
        $this->assertNotNull($conversation->ai_paused_until);

        // Should be ~60 minutes, not 30
        $expectedAt = now()->addMinutes(60);
        $diffSeconds = abs($conversation->ai_paused_until->diffInSeconds($expectedAt));
        $this->assertLessThan(60, $diffSeconds);
    }

    #[Test]
    public function implicit_takeover_fires_conversa_assumida_por_humano_with_motivo_mensagem_enviada(): void
    {
        Event::fake([ConversaAssumidaPorHumano::class]);
        $conversation = $this->makeConversation();

        $this->withHeaders(['Idempotency-Key' => 'test-event-motivo-'.$conversation->id])
            ->postJson(
                $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
                [
                    'content_type' => 'text',
                    'body' => 'Testando evento implícito.',
                ]
            )->assertStatus(201);

        Event::assertDispatched(ConversaAssumidaPorHumano::class, function (ConversaAssumidaPorHumano $event) use ($conversation): bool {
            return $event->conversation->id === $conversation->id
                && $event->reason === 'mensagem_enviada';
        });
    }

    #[Test]
    public function implicit_takeover_only_triggers_when_sender_is_user_not_system(): void
    {
        // System-sent messages (no authenticated user) should NOT trigger pause
        // We simulate by testing that ai_paused_until remains null when no user context
        $conversation = $this->makeConversation();

        // Inject a message as if it were sent by system (no auth user)
        // We do this by directly firing MensagemEnviada with sender_type = system
        $message = Message::factory()
            ->for($conversation)
            ->create([
                'tenant_id' => $this->tenant->id,
                'direction' => 'out',
                'sender_type' => 'system',
                'sender_id' => null,
                'body' => 'System message',
                'content_type' => 'text',
                'status' => 'sent',
            ]);

        $event = new MensagemEnviada($message, $conversation);
        event($event);

        $conversation->refresh();
        // System message must NOT set ai_paused_until
        $this->assertNull($conversation->ai_paused_until);
    }

    #[Test]
    public function implicit_takeover_does_not_trigger_on_inbound_messages(): void
    {
        // Inbound messages (from patient) should NOT set ai_paused_until
        $conversation = $this->makeConversation();

        $message = Message::factory()
            ->for($conversation)
            ->create([
                'tenant_id' => $this->tenant->id,
                'direction' => 'in',
                'sender_type' => 'patient',
                'sender_id' => null,
                'body' => 'Patient message',
                'content_type' => 'text',
                'status' => 'delivered',
            ]);

        // Fire MensagemRecebida (inbound) — listener should not react
        $event = new MensagemRecebida($message, $conversation);
        event($event);

        $conversation->refresh();
        $this->assertNull($conversation->ai_paused_until);
    }
}
