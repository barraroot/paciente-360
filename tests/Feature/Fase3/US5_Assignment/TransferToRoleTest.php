<?php

namespace Tests\Feature\Fase3\US5_Assignment;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Conversation\Models\ConversationAssignment;
use App\Domain\Messaging\Presence\Models\UserPresence;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T141 — Feature tests para transferência para role.
 *
 * Cobre AC-4.5.7, NC-7.b: transferir para role → first online with capacity.
 * Roles permitidas: medico, atendente, recepcionista, admin-clinica.
 * Financeiro excluído (sem inbox.view).
 */
class TransferToRoleTest extends TestCase
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
        [$this->tenant, $this->atendente] = $this->tenantAndUserForRole('clinica-role-transfer', 'atendente');

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
    public function transfer_to_role_medico_picks_first_online_medico_with_capacity(): void
    {
        $medico = $this->userForRole($this->tenant, 'medico');

        UserPresence::factory()
            ->forTenant($this->tenant)
            ->state([
                'user_id' => $medico->id,
                'status' => 'online',
                'last_seen_at' => now()->subMinutes(1),
                'max_concurrent_conversations' => 15,
                'current_assigned_count' => 3,
            ])
            ->create();

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => $this->atendente->id])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/transfer"),
            [
                'role' => 'medico',
                'transfer_note' => 'Paciente quer consultar com médico especialista.',
            ]
        );

        $response->assertOk();
        $conversation->refresh();
        $this->assertEquals($medico->id, $conversation->assigned_user_id);
    }

    #[Test]
    public function transfer_to_role_atendente_picks_first_online_atendente(): void
    {
        $outroAtendente = $this->userForRole($this->tenant, 'atendente');

        UserPresence::factory()
            ->forTenant($this->tenant)
            ->state([
                'user_id' => $outroAtendente->id,
                'status' => 'online',
                'last_seen_at' => now()->subMinutes(1),
                'max_concurrent_conversations' => 15,
                'current_assigned_count' => 2,
            ])
            ->create();

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => $this->atendente->id])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/transfer"),
            [
                'role' => 'atendente',
                'transfer_note' => 'Encaminhando para equipe de atendimento geral.',
            ]
        );

        $response->assertOk();
        $conversation->refresh();
        $this->assertNotNull($conversation->assigned_user_id);
    }

    #[Test]
    public function transfer_to_role_with_no_online_user_falls_back_to_unassigned_queue(): void
    {
        // No online medico
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => $this->atendente->id])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/transfer"),
            [
                'role' => 'medico',
                'transfer_note' => 'Encaminhando para médico mas nenhum disponível.',
            ]
        );

        $response->assertOk();
        $conversation->refresh();
        $this->assertNull($conversation->assigned_user_id);

        // Assignment row records the target role
        $assignment = ConversationAssignment::where('conversation_id', $conversation->id)
            ->where('reason', 'transferencia')
            ->latest('assigned_at')
            ->first();
        $this->assertNotNull($assignment);
        $this->assertEquals('medico', $assignment->assignment_role);
    }

    #[Test]
    public function transfer_role_records_assignment_role_field(): void
    {
        $medico = $this->userForRole($this->tenant, 'medico');

        UserPresence::factory()
            ->forTenant($this->tenant)
            ->state([
                'user_id' => $medico->id,
                'status' => 'online',
                'last_seen_at' => now()->subMinutes(1),
                'max_concurrent_conversations' => 15,
                'current_assigned_count' => 0,
            ])
            ->create();

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => $this->atendente->id])
            ->create();

        $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/transfer"),
            [
                'role' => 'medico',
                'transfer_note' => 'Paciente quer agendar consulta médica urgente.',
            ]
        )->assertOk();

        $assignment = ConversationAssignment::where('conversation_id', $conversation->id)
            ->where('user_id', $medico->id)
            ->first();

        $this->assertNotNull($assignment);
        $this->assertEquals('medico', $assignment->assignment_role);
        $this->assertEquals('transferencia', $assignment->reason);
    }

    #[Test]
    public function transfer_excludes_financeiro_role_from_dropdown_options(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => $this->atendente->id])
            ->create();

        // Financeiro role is not allowed for transfer
        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/transfer"),
            [
                'role' => 'financeiro',
                'transfer_note' => 'Tentando transferir para financeiro é inválido.',
            ]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['role']);
    }
}
