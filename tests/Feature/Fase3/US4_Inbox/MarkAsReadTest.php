<?php

namespace Tests\Feature\Fase3\US4_Inbox;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T097 — Feature tests para marcar conversa como lida (POST /inbox/conversations/{id}/read).
 *
 * Cobre AC-4.4.3 variante (marcar como lido) + broadcast Reverb para sincronização
 * em tempo real entre atendentes.
 *
 * TDD RED: testes devem falhar inicialmente. Endpoint implementado no Lote G.
 */
class MarkAsReadTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $attendent;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->attendent] = $this->tenantAndUserForRole('clinica-read', 'atendente');

        $this->channel = Channel::factory()
            ->forTenant($this->tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    #[Test]
    public function it_marks_conversation_as_read_zeros_unread_count(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['unread_count' => 5])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/read")
        );

        $response->assertNoContent();

        $conversation->refresh();
        $this->assertSame(0, $conversation->unread_count);
    }

    #[Test]
    public function it_only_marks_messages_received_before_now(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['unread_count' => 3])
            ->create();

        // Mensagens antigas (devem ser marcadas como lidas)
        $oldMessage = Message::factory()
            ->forTenant($this->tenant)
            ->for($conversation, 'conversation')
            ->inbound()
            ->state(['created_at' => now()->subHours(1)])
            ->create();

        // Mensagem no futuro (não deve ser marcada como lida)
        $futureMessage = Message::factory()
            ->forTenant($this->tenant)
            ->for($conversation, 'conversation')
            ->inbound()
            ->state(['created_at' => now()->addHours(1)])
            ->create();

        $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/read")
        )->assertNoContent();

        // Mensagem antiga deve ter read_at setado
        $oldMessage->refresh();
        $this->assertNotNull($oldMessage->read_at);

        // Mensagem futura NÃO deve ter read_at setado (posterior ao now() do POST)
        $futureMessage->refresh();
        $this->assertNull($futureMessage->read_at);
    }

    #[Test]
    public function it_requires_inbox_view_ability_for_role_validation(): void
    {
        // Usuário financeiro (sem inbox.view)
        $financeiro = $this->userForRole($this->tenant, 'financeiro');

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['unread_count' => 3])
            ->create();

        $this->actingAs($financeiro);

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/read")
        );

        $response->assertForbidden();
    }

    #[Test]
    public function it_idempotent_re_reading_returns_204_no_op(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['unread_count' => 2])
            ->create();

        // Primeiro read
        $response1 = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/read")
        );
        $response1->assertNoContent();

        $conversation->refresh();
        $this->assertSame(0, $conversation->unread_count);

        // Segundo read (idempotente)
        $response2 = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/read")
        );
        $response2->assertNoContent();

        $conversation->refresh();
        $this->assertSame(0, $conversation->unread_count);
    }
}
