<?php

namespace Tests\Feature\Fase3\US5_Assignment;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Presence\Models\UserPresence;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T139 — Feature tests para auto-atribuição patient_owner (profissional vinculado).
 *
 * Cobre AC-4.5.2, NC-6.a: estratégia patient_owner com fallback round-robin.
 *
 * ATENÇÃO: `pacientes.profissional_responsavel_id` aponta para `professionals.id`,
 * não `users.id`. `Professional.user_id` aponta para `users.id`.
 */
class AssignAutoPatientOwnerTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('clinica-po', 'admin-clinica');

        $this->channel = Channel::factory()
            ->forTenant($this->tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    private function makePatientWithProfissional(User $medicoUser): Paciente
    {
        $professional = Professional::factory()
            ->forTenant($this->tenant)
            ->state(['user_id' => $medicoUser->id])
            ->create();

        return Paciente::factory()
            ->forTenant($this->tenant)
            ->state(['profissional_responsavel_id' => $professional->id])
            ->create();
    }

    private function setPresenceOnline(User $user, int $assigned = 0): void
    {
        UserPresence::factory()
            ->forTenant($this->tenant)
            ->state([
                'user_id' => $user->id,
                'status' => 'online',
                'last_seen_at' => now()->subMinutes(2),
                'max_concurrent_conversations' => 15,
                'current_assigned_count' => $assigned,
            ])
            ->create();
    }

    private function setPresenceOffline(User $user): void
    {
        UserPresence::factory()
            ->forTenant($this->tenant)
            ->state([
                'user_id' => $user->id,
                'status' => 'offline',
                'last_seen_at' => now()->subHours(2),
                'max_concurrent_conversations' => 15,
                'current_assigned_count' => 0,
            ])
            ->create();
    }

    #[Test]
    public function patient_owner_assigns_to_patient_profissional_responsavel_when_online(): void
    {
        $medico = $this->userForRole($this->tenant, 'medico');
        $this->setPresenceOnline($medico);

        $paciente = $this->makePatientWithProfissional($medico);

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => null, 'patient_id' => $paciente->id])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/assign"),
            ['auto' => true, 'strategy' => 'patient_owner']
        );

        $response->assertOk();
        $conversation->refresh();
        $this->assertEquals($medico->id, $conversation->assigned_user_id);
        $this->assertEquals('auto_patient_owner', $conversation->assignment_strategy);
    }

    #[Test]
    public function patient_owner_falls_back_to_round_robin_when_profissional_offline(): void
    {
        $medico = $this->userForRole($this->tenant, 'medico');
        $this->setPresenceOffline($medico);

        $atendente = $this->userForRole($this->tenant, 'atendente');
        $this->setPresenceOnline($atendente);

        $paciente = $this->makePatientWithProfissional($medico);

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => null, 'patient_id' => $paciente->id])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/assign"),
            ['auto' => true, 'strategy' => 'patient_owner']
        );

        $response->assertOk();
        $conversation->refresh();
        // Falls back to atendente via round-robin
        $this->assertEquals($atendente->id, $conversation->assigned_user_id);
        $this->assertEquals('auto_round_robin', $conversation->assignment_strategy);
    }

    #[Test]
    public function patient_owner_falls_back_to_round_robin_when_profissional_at_max_limit(): void
    {
        $medico = $this->userForRole($this->tenant, 'medico');
        $this->setPresenceOnline($medico, assigned: 15); // at limit

        $atendente = $this->userForRole($this->tenant, 'atendente');
        $this->setPresenceOnline($atendente, assigned: 3);

        $paciente = $this->makePatientWithProfissional($medico);

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => null, 'patient_id' => $paciente->id])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/assign"),
            ['auto' => true, 'strategy' => 'patient_owner']
        );

        $response->assertOk();
        $conversation->refresh();
        $this->assertEquals($atendente->id, $conversation->assigned_user_id);
    }

    #[Test]
    public function patient_owner_falls_back_to_round_robin_when_patient_has_no_profissional(): void
    {
        $atendente = $this->userForRole($this->tenant, 'atendente');
        $this->setPresenceOnline($atendente);

        $paciente = Paciente::factory()
            ->forTenant($this->tenant)
            ->state(['profissional_responsavel_id' => null])
            ->create();

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => null, 'patient_id' => $paciente->id])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/assign"),
            ['auto' => true, 'strategy' => 'patient_owner']
        );

        $response->assertOk();
        $conversation->refresh();
        $this->assertEquals($atendente->id, $conversation->assigned_user_id);
        $this->assertEquals('auto_round_robin', $conversation->assignment_strategy);
    }

    #[Test]
    public function patient_owner_falls_back_to_round_robin_when_patient_unidentified(): void
    {
        $atendente = $this->userForRole($this->tenant, 'atendente');
        $this->setPresenceOnline($atendente);

        // Conversation with no patient (unidentified)
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => null, 'patient_id' => null])
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/assign"),
            ['auto' => true, 'strategy' => 'patient_owner']
        );

        $response->assertOk();
        $conversation->refresh();
        $this->assertEquals($atendente->id, $conversation->assigned_user_id);
        $this->assertEquals('auto_round_robin', $conversation->assignment_strategy);
    }
}
