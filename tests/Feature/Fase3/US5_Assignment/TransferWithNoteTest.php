<?php

namespace Tests\Feature\Fase3\US5_Assignment;

use App\Domain\Messaging\Assignment\Events\ConversaTransferida;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Conversation\Models\ConversationAssignment;
use App\Domain\Messaging\Presence\Models\UserPresence;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T140 — Feature tests para POST /inbox/conversations/{id}/transfer com nota.
 *
 * Cobre AC-4.5.3 (nota mínimo 10 chars), AC-4.5.4 (histórico append-only), NC-7.a.
 */
class TransferWithNoteTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $atendente;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->atendente] = $this->tenantAndUserForRole('clinica-transfer', 'atendente');

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
    public function transfer_with_note_min_10_chars_succeeds(): void
    {
        $destino = $this->userForRole($this->tenant, 'atendente');

        UserPresence::factory()
            ->forTenant($this->tenant)
            ->state(['user_id' => $destino->id, 'status' => 'online', 'last_seen_at' => now()->subMinutes(1)])
            ->create();

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => $this->atendente->id])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/transfer"),
            [
                'user_id' => $destino->id,
                'transfer_note' => 'Encaminhando para especialista médico.',
            ]
        );

        $response->assertOk();
        $conversation->refresh();
        $this->assertEquals($destino->id, $conversation->assigned_user_id);
        $this->assertEquals('transfer', $conversation->assignment_strategy);
    }

    #[Test]
    public function transfer_rejects_note_below_10_chars(): void
    {
        $destino = $this->userForRole($this->tenant, 'atendente');

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => $this->atendente->id])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/transfer"),
            [
                'user_id' => $destino->id,
                'transfer_note' => 'Curto',
            ]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['transfer_note']);
    }

    #[Test]
    public function transfer_rejects_empty_note(): void
    {
        $destino = $this->userForRole($this->tenant, 'atendente');

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => $this->atendente->id])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/transfer"),
            [
                'user_id' => $destino->id,
                'transfer_note' => '',
            ]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['transfer_note']);
    }

    #[Test]
    public function transfer_creates_append_only_history_row(): void
    {
        $destino = $this->userForRole($this->tenant, 'atendente');

        UserPresence::factory()
            ->forTenant($this->tenant)
            ->state(['user_id' => $destino->id, 'status' => 'online', 'last_seen_at' => now()->subMinutes(1)])
            ->create();

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => $this->atendente->id])
            ->create();

        // Initial assignment row
        ConversationAssignment::factory()
            ->forTenant($this->tenant)
            ->state([
                'conversation_id' => $conversation->id,
                'user_id' => $this->atendente->id,
                'assigned_by' => null,
                'assigned_at' => now()->subHour(),
                'reason' => 'inicial',
            ])
            ->create();

        $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/transfer"),
            [
                'user_id' => $destino->id,
                'transfer_note' => 'Paciente precisa de médico especialista.',
            ]
        )->assertOk();

        // Now should have 2 rows — append-only
        $count = ConversationAssignment::where('conversation_id', $conversation->id)->count();
        $this->assertEquals(2, $count);
    }

    #[Test]
    public function transfer_marks_previous_assignment_unassigned_at(): void
    {
        $destino = $this->userForRole($this->tenant, 'atendente');

        UserPresence::factory()
            ->forTenant($this->tenant)
            ->state(['user_id' => $destino->id, 'status' => 'online', 'last_seen_at' => now()->subMinutes(1)])
            ->create();

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => $this->atendente->id])
            ->create();

        $initialAssignment = ConversationAssignment::factory()
            ->forTenant($this->tenant)
            ->state([
                'conversation_id' => $conversation->id,
                'user_id' => $this->atendente->id,
                'assigned_at' => now()->subHour(),
                'unassigned_at' => null,
                'reason' => 'inicial',
            ])
            ->create();

        $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/transfer"),
            [
                'user_id' => $destino->id,
                'transfer_note' => 'Encaminhando para quem pode ajudar.',
            ]
        )->assertOk();

        $initialAssignment->refresh();
        $this->assertNotNull($initialAssignment->unassigned_at);
    }

    #[Test]
    public function transfer_fires_conversa_transferida_event(): void
    {
        Event::fake();

        $destino = $this->userForRole($this->tenant, 'atendente');

        UserPresence::factory()
            ->forTenant($this->tenant)
            ->state(['user_id' => $destino->id, 'status' => 'online', 'last_seen_at' => now()->subMinutes(1)])
            ->create();

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => $this->atendente->id])
            ->create();

        $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/transfer"),
            [
                'user_id' => $destino->id,
                'transfer_note' => 'Transferindo para especialista responsável.',
            ]
        )->assertOk();

        Event::assertDispatched(ConversaTransferida::class);
    }

    #[Test]
    public function transfer_assignment_history_preserved_when_subsequent_transfers(): void
    {
        $destino1 = $this->userForRole($this->tenant, 'atendente');
        $destino2 = $this->userForRole($this->tenant, 'medico');

        UserPresence::factory()
            ->forTenant($this->tenant)
            ->state(['user_id' => $destino1->id, 'status' => 'online', 'last_seen_at' => now()->subMinutes(1)])
            ->create();
        UserPresence::factory()
            ->forTenant($this->tenant)
            ->state(['user_id' => $destino2->id, 'status' => 'online', 'last_seen_at' => now()->subMinutes(1)])
            ->create();

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => $this->atendente->id])
            ->create();

        ConversationAssignment::factory()
            ->forTenant($this->tenant)
            ->state([
                'conversation_id' => $conversation->id,
                'user_id' => $this->atendente->id,
                'reason' => 'inicial',
            ])
            ->create();

        $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/transfer"),
            ['user_id' => $destino1->id, 'transfer_note' => 'Primeira transferência para atendente.']
        )->assertOk();

        // Re-auth as destino1 for second transfer
        Sanctum::actingAs($destino1, ['*']);
        $this->app->instance('tenant', $this->tenant);

        $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/transfer"),
            ['user_id' => $destino2->id, 'transfer_note' => 'Segunda transferência para médico.']
        )->assertOk();

        // 3 rows total (1 inicial + 2 transferências)
        $count = ConversationAssignment::where('conversation_id', $conversation->id)->count();
        $this->assertEquals(3, $count);

        // GET assignments endpoint returns chronological history
        $historyResponse = $this->getJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/assignments")
        );
        $historyResponse->assertOk();
        $historyResponse->assertJsonCount(3, 'data');
    }

    #[Test]
    public function requires_inbox_transfer_ability(): void
    {
        $financeiro = $this->userForRole($this->tenant, 'financeiro');
        Sanctum::actingAs($financeiro, ['*']);
        $this->app->instance('tenant', $this->tenant);

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->create();

        $destino = $this->userForRole($this->tenant, 'atendente');

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/transfer"),
            ['user_id' => $destino->id, 'transfer_note' => 'Nota de transferência para teste.']
        );

        $response->assertForbidden();
    }
}
