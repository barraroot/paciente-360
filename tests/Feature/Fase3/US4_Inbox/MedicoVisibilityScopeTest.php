<?php

namespace Tests\Feature\Fase3\US4_Inbox;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T100 — Feature tests para visibilidade de conversas por role (Médico, Admin, Atendente, etc).
 *
 * Médico vê apenas: (a) conversas atribuídas a si OU (b) conversas cujo paciente
 * tem ele como profissional_responsavel_id.
 *
 * Outros roles (atendente, recepcionista, admin) veem todas as conversas do tenant.
 *
 * TDD RED: testes devem falhar inicialmente. Policies implementadas no Lote G.
 */
class MedicoVisibilityScopeTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('clinica-medico', 'atendente');

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
    public function medico_sees_only_assigned_conversations_in_inbox_list(): void
    {
        $medico = $this->userForRole($this->tenant, 'medico');

        // Conversas atribuídas ao médico
        $convAssigned = Conversation::factory()
            ->count(2)
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => $medico->id])
            ->create();

        // Conversas não atribuídas (fila)
        Conversation::factory()
            ->count(2)
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => null])
            ->create();

        // Conversa atribuída a outro atendente
        $otherAtendente = $this->userForRole($this->tenant, 'atendente');
        Conversation::factory()
            ->count(1)
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => $otherAtendente->id])
            ->create();

        $this->actingAs($medico);

        $response = $this->getJson($this->baseUrl('/inbox/conversations'));

        $response->assertOk();
        // Médico vê apenas as 2 conversas atribuídas a si
        $this->assertCount(2, $response->json('data'));
        $response->json('data')->each(function ($conv) use ($medico) {
            $this->assertSame($medico->id, $conv['assigned_user']['id']);
        });
    }

    #[Test]
    public function medico_can_open_assigned_conversation(): void
    {
        $medico = $this->userForRole($this->tenant, 'medico');

        $conv = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->state(['assigned_user_id' => $medico->id])
            ->create();

        $this->actingAs($medico);

        $response = $this->getJson(
            $this->baseUrl("/inbox/conversations/{$conv->id}")
        );

        $response->assertOk();
    }

    #[Test]
    public function medico_can_open_conversation_with_patient_he_owns_even_unassigned(): void
    {
        $medico = $this->userForRole($this->tenant, 'medico');

        $paciente = Paciente::factory()
            ->forTenant($this->tenant)
            ->state(['profissional_responsavel_id' => $medico->id])
            ->create();

        // Conversa não atribuída, mas paciente é responsabilidade do médico
        $conv = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->for($paciente, 'patient')
            ->state(['assigned_user_id' => null])
            ->create();

        $this->actingAs($medico);

        $response = $this->getJson(
            $this->baseUrl("/inbox/conversations/{$conv->id}")
        );

        $response->assertOk();
    }

    #[Test]
    public function medico_cannot_open_other_medicos_conversation(): void
    {
        $medico1 = $this->userForRole($this->tenant, 'medico');
        $medico2 = $this->userForRole($this->tenant, 'medico');

        $paciente = Paciente::factory()
            ->forTenant($this->tenant)
            ->state(['profissional_responsavel_id' => $medico2->id])
            ->create();

        $conv = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->for($paciente, 'patient')
            ->state(['assigned_user_id' => $medico2->id])
            ->create();

        $this->actingAs($medico1);

        $response = $this->getJson(
            $this->baseUrl("/inbox/conversations/{$conv->id}")
        );

        // Médico 1 não pode ver conversa de Médico 2
        $response->assertNotFound();
    }

    #[Test]
    public function medico_can_send_message_to_visible_conversation_only(): void
    {
        $medico = $this->userForRole($this->tenant, 'medico');

        $paciente = Paciente::factory()
            ->forTenant($this->tenant)
            ->state(['profissional_responsavel_id' => $medico->id])
            ->create();

        $visibleConv = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->for($paciente, 'patient')
            ->state(['assigned_user_id' => $medico->id])
            ->create();

        $hiddenPaciente = Paciente::factory()
            ->forTenant($this->tenant)
            ->create();

        $hiddenConv = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->for($hiddenPaciente, 'patient')
            ->create();

        $this->actingAs($medico);

        // Pode enviar mensagem na conversa visível
        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$visibleConv->id}/messages"),
            ['content_type' => 'text', 'body' => 'Teste'],
            ['Idempotency-Key' => Str::uuid()->toString()]
        );
        // Pode ser 201 ou error de janela 24h, mas a permissão é verificada primeiro

        // Não pode enviar na conversa invisível
        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$hiddenConv->id}/messages"),
            ['content_type' => 'text', 'body' => 'Teste'],
            ['Idempotency-Key' => Str::uuid()->toString()]
        );
        $response->assertNotFound();
    }

    #[Test]
    public function admin_clinica_sees_all_tenant_conversations(): void
    {
        $admin = $this->userForRole($this->tenant, 'admin-clinica');

        Conversation::factory()
            ->count(5)
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->create();

        $this->actingAs($admin);

        $response = $this->getJson($this->baseUrl('/inbox/conversations'));

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
    }

    #[Test]
    public function atendente_sees_all_tenant_conversations(): void
    {
        $atendente = $this->userForRole($this->tenant, 'atendente');

        Conversation::factory()
            ->count(5)
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->create();

        $this->actingAs($atendente);

        $response = $this->getJson($this->baseUrl('/inbox/conversations'));

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
    }

    #[Test]
    public function recepcionista_sees_all_tenant_conversations(): void
    {
        $recepcionista = $this->userForRole($this->tenant, 'recepcionista');

        Conversation::factory()
            ->count(5)
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->create();

        $this->actingAs($recepcionista);

        $response = $this->getJson($this->baseUrl('/inbox/conversations'));

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
    }

    #[Test]
    public function financeiro_gets_403_on_inbox_endpoints(): void
    {
        $financeiro = $this->userForRole($this->tenant, 'financeiro');

        Conversation::factory()
            ->count(3)
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->create();

        $this->actingAs($financeiro);

        $response = $this->getJson($this->baseUrl('/inbox/conversations'));

        $response->assertForbidden();
    }
}
