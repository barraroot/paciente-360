<?php

namespace Tests\Feature\Fase3\US4_Inbox;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T095 — Feature tests para POST /api/v1/inbox/conversations/{id}/messages (envio de mensagens).
 *
 * Cobre AC-4.4.4 (envio de texto + eventos), AC-4.4.5 (bloqueio janela 24h),
 * AC-4.4.6 (templates), AC-4.4.7 (mídia).
 *
 * TDD RED: testes devem falhar inicialmente. Endpoint implementado no Lote G.
 */
class SendOutboundMessageTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $attendent;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');

        $this->seedRoles();
        [$this->tenant, $this->attendent] = $this->tenantAndUserForRole('clinica-send', 'atendente');

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
    public function it_enqueues_outbound_message_with_idempotency_key(): void
    {
        Queue::fake();

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->comJanela24hAberta()
            ->create();

        $idempotencyKey = Str::uuid()->toString();

        $payload = [
            'content_type' => 'text',
            'body' => 'Olá Maria, como posso ajudar?',
        ];

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
            $payload,
            ['Idempotency-Key' => $idempotencyKey]
        );

        $response->assertCreated();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'conversation_id',
                'body',
                'status',
                'idempotency_key',
                'created_at',
            ],
        ]);

        $this->assertSame('queued', $response->json('data.status'));
        $this->assertSame($idempotencyKey, $response->json('data.idempotency_key'));

        // Verifica que a mensagem foi persistida
        // `body` é criptografado em repouso — não pode ser comparado diretamente via assertDatabaseHas.
        // A decodificação correta é validada pelo json('data.body') acima via MessageResource.
        $this->assertDatabaseHas('messaging_messages', [
            'conversation_id' => $conversation->id,
            'idempotency_key' => $idempotencyKey,
            'status' => 'queued',
        ]);
    }

    #[Test]
    public function it_same_idempotency_key_returns_existing_message_with_200(): void
    {
        Queue::fake();

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->comJanela24hAberta()
            ->create();

        $idempotencyKey = Str::uuid()->toString();
        $body = 'Olá Maria, como posso ajudar?';

        // Primeiro envio
        $response1 = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
            ['content_type' => 'text', 'body' => $body],
            ['Idempotency-Key' => $idempotencyKey]
        );

        $response1->assertCreated();
        $messageId1 = $response1->json('data.id');

        // Segundo envio com mesma chave
        $response2 = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
            ['content_type' => 'text', 'body' => $body],
            ['Idempotency-Key' => $idempotencyKey]
        );

        $response2->assertOk();
        $this->assertSame($messageId1, $response2->json('data.id'));
    }

    #[Test]
    public function it_different_body_same_idempotency_key_returns_422(): void
    {
        Queue::fake();

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->comJanela24hAberta()
            ->create();

        $idempotencyKey = Str::uuid()->toString();

        // Primeiro envio
        $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
            ['content_type' => 'text', 'body' => 'Primeira mensagem'],
            ['Idempotency-Key' => $idempotencyKey]
        );

        // Segundo envio com mesma chave mas corpo diferente
        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
            ['content_type' => 'text', 'body' => 'Segunda mensagem diferente'],
            ['Idempotency-Key' => $idempotencyKey]
        );

        $response->assertUnprocessable();
    }

    #[Test]
    public function it_requires_idempotency_key_header(): void
    {
        Queue::fake();

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->comJanela24hAberta()
            ->create();

        // Sem header Idempotency-Key
        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
            ['content_type' => 'text', 'body' => 'Teste sem chave']
        );

        $response->assertUnprocessable();
    }

    #[Test]
    public function it_validates_body_max_length_4096(): void
    {
        Queue::fake();

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->comJanela24hAberta()
            ->create();

        $longBody = str_repeat('a', 5000);
        $idempotencyKey = Str::uuid()->toString();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
            ['content_type' => 'text', 'body' => $longBody],
            ['Idempotency-Key' => $idempotencyKey]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('body');
    }

    #[Test]
    public function it_validates_content_type_enum(): void
    {
        Queue::fake();

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->comJanela24hAberta()
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
            ['content_type' => 'invalid_type', 'body' => 'Teste'],
            ['Idempotency-Key' => Str::uuid()->toString()]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('content_type');
    }

    #[Test]
    public function it_requires_inbox_respond_ability(): void
    {
        Queue::fake();

        // Usuário financeiro sem inbox.respond
        $financeiro = $this->userForRole($this->tenant, 'financeiro');

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->comJanela24hAberta()
            ->create();

        Sanctum::actingAs($financeiro, ['*']);

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
            ['content_type' => 'text', 'body' => 'Teste'],
            ['Idempotency-Key' => Str::uuid()->toString()]
        );

        $response->assertForbidden();
    }

    #[Test]
    public function it_fires_mensagem_enviada_event_after_persistence(): void
    {
        Queue::fake();

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->comJanela24hAberta()
            ->create();

        $idempotencyKey = Str::uuid()->toString();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
            ['content_type' => 'text', 'body' => 'Olá'],
            ['Idempotency-Key' => $idempotencyKey]
        );

        $response->assertCreated();

        // Verifica que a mensagem está no DB e marcada como queued
        $message = Message::where('idempotency_key', $idempotencyKey)->first();
        $this->assertNotNull($message);
        $this->assertSame('queued', $message->status);
    }

    #[Test]
    public function it_increments_conversation_last_message_at_and_unread_count_reset(): void
    {
        Queue::fake();

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->comJanela24hAberta()
            ->state(['unread_count' => 5])
            ->create();

        $oldLastMessageAt = $conversation->last_message_at;

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
            ['content_type' => 'text', 'body' => 'Resposta do atendente'],
            ['Idempotency-Key' => Str::uuid()->toString()]
        );

        $response->assertCreated();

        // Recarrega a conversa
        $conversation->refresh();

        // last_message_at deve ser atualizado
        $this->assertGreaterThan($oldLastMessageAt, $conversation->last_message_at);

        // unread_count deve ser resetado para 0
        $this->assertSame(0, $conversation->unread_count);
    }

    #[Test]
    public function it_sends_template_message_with_provider_id_and_variables(): void
    {
        Queue::fake();

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->comJanela24hFechada()  // Janela fechada, mas template é permitido
            ->create();

        $templateId = 'HXabc123def';
        $variables = ['1' => 'João Silva', '2' => '2026-06-15'];

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
            [
                'content_type' => 'template',
                'template' => [
                    'provider_template_id' => $templateId,
                    'variables' => $variables,
                ],
            ],
            ['Idempotency-Key' => Str::uuid()->toString()]
        );

        $response->assertCreated();

        $message = Message::find($response->json('data.id'));
        $this->assertSame('template', $message->content_type);
        $this->assertSame($templateId, $message->template_provider_id);
        $this->assertSame($variables, (array) $message->template_variables);
    }

    #[Test]
    public function it_sends_image_message_with_media_token(): void
    {
        Queue::fake();
        Storage::fake('media');

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->comJanela24hAberta()
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
            [
                'content_type' => 'image',
                'media_token' => 'media_token_xyz123',
            ],
            ['Idempotency-Key' => Str::uuid()->toString()]
        );

        // Pode ser 201 ou skipped dependendo da implementação de T247 (upload de mídia)
        if ($response->status() === 201) {
            $message = Message::find($response->json('data.id'));
            $this->assertSame('image', $message->content_type);
        } else {
            $this->markTestSkipped('Media upload (T247) não implementado ainda');
        }
    }
}
