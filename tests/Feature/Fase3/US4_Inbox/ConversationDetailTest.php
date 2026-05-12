<?php

namespace Tests\Feature\Fase3\US4_Inbox;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Message\Models\MessageMedia;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T094 — Feature tests para GET /api/v1/inbox/conversations/{id} e /messages.
 *
 * Cobre AC-4.4.3 (detalhe de conversa + histórico de mensagens), AC-4.4.12 (isolamento).
 *
 * TDD RED: testes devem falhar inicialmente. Endpoints implementados no Lote G.
 */
class ConversationDetailTest extends TestCase
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
        [$this->tenant, $this->attendent] = $this->tenantAndUserForRole('clinica-detail', 'atendente');

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
    public function it_shows_conversation_with_full_resource_shape(): void
    {
        $paciente = Paciente::factory()
            ->forTenant($this->tenant)
            ->create();

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->for($paciente, 'patient')
            ->state([
                'assigned_user_id' => $this->attendent->id,
                'ai_paused_until' => now()->addHours(2),
                'last_inbound_message_at' => now()->subMinutes(30),
            ])
            ->create();

        $response = $this->getJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}")
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'channel' => ['id', 'type', 'name'],
                'patient' => ['id', 'name'],
                'status',
                'assigned_user' => ['id', 'name'],
                'last_message_at',
                'last_inbound_message_at',
                'unread_count',
                'ai_paused_until',
                'window_24h' => ['open', 'seconds_remaining'],
            ],
        ]);

        $this->assertSame($conversation->id, $response->json('data.id'));
        $this->assertSame($paciente->id, $response->json('data.patient.id'));
        $this->assertSame($this->attendent->id, $response->json('data.assigned_user.id'));
        $this->assertNotNull($response->json('data.ai_paused_until'));
    }

    #[Test]
    public function it_returns_404_for_non_existent_or_other_tenant_conversation(): void
    {
        $response = $this->getJson(
            $this->baseUrl('/inbox/conversations/99999')
        );

        $response->assertNotFound();
    }

    #[Test]
    public function it_includes_window_24h_open_true_when_last_inbound_recent(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['last_inbound_message_at' => now()->subMinutes(30)])
            ->create();

        $response = $this->getJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}")
        );

        $response->assertOk();
        $this->assertTrue($response->json('data.window_24h.open'));
        $this->assertGreaterThan(0, $response->json('data.window_24h.seconds_remaining'));
    }

    #[Test]
    public function it_includes_window_24h_open_false_when_last_inbound_older_than_24h(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['last_inbound_message_at' => now()->subHours(25)])
            ->create();

        $response = $this->getJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}")
        );

        $response->assertOk();
        $this->assertFalse($response->json('data.window_24h.open'));
        $this->assertNull($response->json('data.window_24h.seconds_remaining'));
    }

    #[Test]
    public function it_lists_messages_paginated_by_cursor(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->create();

        // Cria 60 mensagens (primeira página retorna 50)
        Message::factory()
            ->count(60)
            ->forTenant($this->tenant)
            ->for($conversation, 'conversation')
            ->inbound()
            ->create();

        $response = $this->getJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages")
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'body',
                    'status',
                    'sender_type',
                    'created_at',
                ],
            ],
            'meta' => [
                'next_cursor',
                'has_more',
            ],
        ]);

        $this->assertCount(50, $response->json('data'));
        $this->assertTrue($response->json('meta.has_more'));
        $this->assertNotNull($response->json('meta.next_cursor'));
    }

    #[Test]
    public function it_orders_messages_chronologically_reverse(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->create();

        $msg1 = Message::factory()
            ->forTenant($this->tenant)
            ->for($conversation, 'conversation')
            ->state(['created_at' => now()->subHours(3)])
            ->create();

        $msg2 = Message::factory()
            ->forTenant($this->tenant)
            ->for($conversation, 'conversation')
            ->state(['created_at' => now()->subHours(1)])
            ->create();

        $msg3 = Message::factory()
            ->forTenant($this->tenant)
            ->for($conversation, 'conversation')
            ->state(['created_at' => now()])
            ->create();

        $response = $this->getJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages")
        );

        $response->assertOk();
        $data = $response->json('data');
        // Ordem DESC (mais recentes primeiro)
        $this->assertSame($msg3->id, $data[0]['id']);
        $this->assertSame($msg2->id, $data[1]['id']);
        $this->assertSame($msg1->id, $data[2]['id']);
    }

    #[Test]
    public function it_messages_body_is_decrypted_in_resource(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->create();

        $message = Message::factory()
            ->forTenant($this->tenant)
            ->for($conversation, 'conversation')
            ->state([
                'body' => 'PII secreto que deve ser decriptado',
                'body_searchable' => 'pii secreto que deve ser decriptado',
            ])
            ->create();

        $response = $this->getJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages")
        );

        $response->assertOk();
        // Body deve estar desencriptado no resource
        $this->assertSame(
            $message->body,
            $response->json('data.0.body')
        );
    }

    #[Test]
    public function it_messages_include_media_with_signed_url_24h(): void
    {
        Storage::fake('media');

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->create();

        $message = Message::factory()
            ->forTenant($this->tenant)
            ->for($conversation, 'conversation')
            ->image()
            ->create();

        $media = MessageMedia::factory()
            ->forTenant($this->tenant)
            ->for($message, 'message')
            ->state([
                'storage_disk' => 'media',
                'storage_path' => "tenant_{$this->tenant->id}/conversa_{$conversation->id}/msg_{$message->id}/image.jpg",
                'mime_type' => 'image/jpeg',
            ])
            ->create();

        $response = $this->getJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages")
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'media' => [
                        '*' => [
                            'id',
                            'download_url',
                            'mime_type',
                            'size_bytes',
                        ],
                    ],
                ],
            ],
        ]);

        $downloadUrl = $response->json('data.0.media.0.download_url');
        $this->assertNotEmpty($downloadUrl);
        $this->assertStringContainsString('s=', $downloadUrl); // Signed URL marker
    }

    #[Test]
    public function it_messages_filter_by_role_medico_visibility(): void
    {
        // Cria médico vinculado a um paciente
        $medico = $this->userForRole($this->tenant, 'medico');
        $paciente = Paciente::factory()
            ->forTenant($this->tenant)
            ->state(['profissional_responsavel_id' => $medico->id])
            ->create();

        // Conversa 1: atribuída ao médico
        $conv1 = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->for($paciente, 'patient')
            ->state(['assigned_user_id' => $medico->id])
            ->create();

        // Conversa 2: não atribuída ao médico, paciente sem ele como responsável
        $otherPaciente = Paciente::factory()
            ->forTenant($this->tenant)
            ->create();
        $conv2 = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->for($otherPaciente, 'patient')
            ->create();

        $this->actingAs($medico);

        // Médico pode acessar conversa 1
        $response = $this->getJson(
            $this->baseUrl("/inbox/conversations/{$conv1->id}")
        );
        $response->assertOk();

        // Médico NÃO pode acessar conversa 2
        $response = $this->getJson(
            $this->baseUrl("/inbox/conversations/{$conv2->id}")
        );
        $response->assertNotFound();
    }

    #[Test]
    public function it_requires_inbox_view_ability_for_role_validation(): void
    {
        // Usuário financeiro (sem inbox.view)
        $financeiro = $this->userForRole($this->tenant, 'financeiro');

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->create();

        $this->actingAs($financeiro);

        $response = $this->getJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}")
        );

        $response->assertForbidden();
    }
}
