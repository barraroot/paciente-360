<?php

namespace Tests\Feature\Fase3\US4_Inbox;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T092 — Feature tests para GET /api/v1/inbox/conversations (listagem e filtros).
 *
 * Cobre AC-4.4.1 (lista unificada) + AC-4.4.8 (filtros: status, canal, atendente, paciente, AI pausada, atividade).
 * Cobre AC-4.4.12 (isolamento multi-tenant).
 *
 * TDD RED: testes devem falhar inicialmente pois controller/routes ainda não existem.
 */
class InboxListConversationsTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $attendent;

    private Channel $whatsappChannel;

    private Channel $instagramChannel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->attendent] = $this->tenantAndUserForRole('clinica-inbox', 'atendente');

        // Cria canais de teste
        $this->whatsappChannel = Channel::factory()
            ->forTenant($this->tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();

        $this->instagramChannel = Channel::factory()
            ->forTenant($this->tenant)
            ->state(['type' => 'instagram', 'status' => 'ativo'])
            ->create();
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    #[Test]
    public function it_lists_paginated_conversations_for_tenant(): void
    {
        // Cria 30 conversas (25 na primeira página, 5 na segunda)
        $conversations = Conversation::factory()
            ->count(30)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->create();

        $response = $this->getJson($this->baseUrl('/inbox/conversations'));

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'channel' => ['id', 'type', 'name'],
                    'patient' => ['id', 'name'],
                    'status',
                    'assigned_user' => ['id', 'name'],
                    'last_message_at',
                    'unread_count',
                    'window_24h' => ['open', 'seconds_remaining'],
                ],
            ],
            'meta' => [
                'total',
                'per_page',
                'current_page',
                'last_page',
            ],
        ]);

        $this->assertCount(25, $response->json('data'));
        $this->assertSame(30, $response->json('meta.total'));
        $this->assertSame(25, $response->json('meta.per_page'));
    }

    #[Test]
    public function it_filters_by_status_multi_select(): void
    {
        Conversation::factory()
            ->count(5)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->aberta()
            ->create();

        Conversation::factory()
            ->count(3)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->pendente()
            ->create();

        Conversation::factory()
            ->count(2)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->resolvida()
            ->create();

        $response = $this->getJson(
            $this->baseUrl('/inbox/conversations?status[]=aberta&status[]=pendente')
        );

        $response->assertOk();
        $this->assertCount(8, $response->json('data'));
    }

    #[Test]
    public function it_filters_by_channel_type(): void
    {
        Conversation::factory()
            ->count(5)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->create();

        Conversation::factory()
            ->count(3)
            ->forTenant($this->tenant)
            ->for($this->instagramChannel, 'channel')
            ->create();

        $response = $this->getJson(
            $this->baseUrl('/inbox/conversations?channel_type=whatsapp')
        );

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
        collect($response->json('data.*.channel.type'))->each(
            fn ($type) => $this->assertSame('whatsapp', $type)
        );
    }

    #[Test]
    public function it_filters_by_channel_id(): void
    {
        Conversation::factory()
            ->count(5)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->create();

        Conversation::factory()
            ->count(3)
            ->forTenant($this->tenant)
            ->for($this->instagramChannel, 'channel')
            ->create();

        $response = $this->getJson(
            $this->baseUrl("/inbox/conversations?channel_id={$this->whatsappChannel->id}")
        );

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
    }

    #[Test]
    public function it_filters_by_assigned_user_me_alias(): void
    {
        // Conversas atribuídas ao atendente autenticado
        Conversation::factory()
            ->count(3)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->state(['assigned_user_id' => $this->attendent->id])
            ->create();

        // Conversas atribuídas a outro atendente
        $otherAttendent = $this->createUserForTenant($this->tenant);
        Conversation::factory()
            ->count(2)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->state(['assigned_user_id' => $otherAttendent->id])
            ->create();

        $response = $this->getJson(
            $this->baseUrl('/inbox/conversations?assigned_user_id=me')
        );

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    #[Test]
    public function it_filters_by_assigned_user_unassigned_alias(): void
    {
        // Conversas sem atendente (fila)
        Conversation::factory()
            ->count(4)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->state(['assigned_user_id' => null])
            ->create();

        // Conversas atribuídas
        Conversation::factory()
            ->count(3)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->state(['assigned_user_id' => $this->attendent->id])
            ->create();

        $response = $this->getJson(
            $this->baseUrl('/inbox/conversations?assigned_user_id=unassigned')
        );

        $response->assertOk();
        $this->assertCount(4, $response->json('data'));
    }

    #[Test]
    public function it_filters_by_patient_id(): void
    {
        $paciente = Paciente::factory()
            ->forTenant($this->tenant)
            ->create();

        // Conversas do paciente
        Conversation::factory()
            ->count(3)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->for($paciente, 'patient')
            ->create();

        // Conversas de outro paciente
        $otherPaciente = Paciente::factory()
            ->forTenant($this->tenant)
            ->create();
        Conversation::factory()
            ->count(2)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->for($otherPaciente, 'patient')
            ->create();

        $response = $this->getJson(
            $this->baseUrl("/inbox/conversations?patient_id={$paciente->id}")
        );

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    #[Test]
    public function it_filters_by_ai_paused_true(): void
    {
        // Conversas com IA pausada
        Conversation::factory()
            ->count(2)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->state(['ai_paused_until' => now()->addHours(2)])
            ->create();

        // Conversas sem pausa de IA
        Conversation::factory()
            ->count(3)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->state(['ai_paused_until' => null])
            ->create();

        $response = $this->getJson(
            $this->baseUrl('/inbox/conversations?ai_paused=true')
        );

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    #[Test]
    public function it_filters_by_last_activity_range(): void
    {
        $startDate = now()->subDays(10);
        $endDate = now()->subDays(5);

        // Conversas com última mensagem dentro do intervalo
        Conversation::factory()
            ->count(2)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->state(['last_message_at' => $startDate->addDays(2)])
            ->create();

        // Conversas fora do intervalo
        Conversation::factory()
            ->count(2)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->state(['last_message_at' => now()->subDays(20)])
            ->create();

        $response = $this->getJson(
            $this->baseUrl("/inbox/conversations?last_activity_from={$startDate->toIso8601String()}&last_activity_to={$endDate->toIso8601String()}")
        );

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    #[Test]
    public function it_combines_filters_with_and_logic(): void
    {
        Conversation::factory()
            ->count(3)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->aberta()
            ->state(['assigned_user_id' => $this->attendent->id])
            ->create();

        Conversation::factory()
            ->count(2)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->pendente()
            ->state(['assigned_user_id' => $this->attendent->id])
            ->create();

        Conversation::factory()
            ->count(2)
            ->forTenant($this->tenant)
            ->for($this->instagramChannel, 'channel')
            ->aberta()
            ->state(['assigned_user_id' => $this->attendent->id])
            ->create();

        // Filtra por status=aberta AND channel_type=whatsapp AND assigned_user_id=me
        $response = $this->getJson(
            $this->baseUrl('/inbox/conversations?status[]=aberta&channel_type=whatsapp&assigned_user_id=me')
        );

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    #[Test]
    public function it_orders_by_last_message_at_desc_by_default(): void
    {
        $conv1 = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->state(['last_message_at' => now()->subHours(2)])
            ->create();

        $conv2 = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->state(['last_message_at' => now()->subHours(5)])
            ->create();

        $conv3 = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->state(['last_message_at' => now()])
            ->create();

        $response = $this->getJson($this->baseUrl('/inbox/conversations'));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertSame($conv3->id, $data[0]['id']);
        $this->assertSame($conv1->id, $data[1]['id']);
        $this->assertSame($conv2->id, $data[2]['id']);
    }

    #[Test]
    public function it_returns_aggregations_in_meta_for_badges(): void
    {
        Conversation::factory()
            ->count(3)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->aberta()
            ->create();

        Conversation::factory()
            ->count(2)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->pendente()
            ->create();

        Conversation::factory()
            ->count(4)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->state(['assigned_user_id' => null])
            ->create();

        Conversation::factory()
            ->count(2)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->state(['assigned_user_id' => $this->attendent->id])
            ->create();

        $response = $this->getJson($this->baseUrl('/inbox/conversations'));

        $response->assertOk();
        $response->assertJsonStructure([
            'meta' => [
                'by_status' => ['aberta', 'pendente', 'resolvida', 'reaberta'],
                'unassigned',
                'mine',
            ],
        ]);

        // 3 explicit aberta + 4 null-assigned (default aberta) + 2 mine-assigned (default aberta) = 9 aberta total
        $this->assertSame(9, $response->json('meta.by_status.aberta'));
        $this->assertSame(2, $response->json('meta.by_status.pendente'));
        // 3 aberta + 2 pendente + 4 null-assigned = 9 conversations with null assigned_user_id
        $this->assertSame(9, $response->json('meta.unassigned'));
        $this->assertSame(2, $response->json('meta.mine'));
    }

    #[Test]
    public function it_requires_inbox_view_ability(): void
    {
        // Usuário sem ability inbox.view
        $user = $this->createUserForTenant($this->tenant);
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson($this->baseUrl('/inbox/conversations'));

        $response->assertForbidden();
    }

    #[Test]
    public function it_isolates_tenants(): void
    {
        // Cria outro tenant
        $otherTenant = $this->bootstrapTenantWithRoles('clinica-other');
        $otherChannel = Channel::factory()
            ->forTenant($otherTenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();

        // Conversas do tenant atual
        Conversation::factory()
            ->count(3)
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->create();

        // Conversas do outro tenant
        Conversation::factory()
            ->count(5)
            ->forTenant($otherTenant)
            ->for($otherChannel, 'channel')
            ->create();

        $response = $this->getJson($this->baseUrl('/inbox/conversations'));

        $response->assertOk();
        // Deve retornar apenas as 3 conversas do tenant atual
        $this->assertCount(3, $response->json('data'));
    }
}
