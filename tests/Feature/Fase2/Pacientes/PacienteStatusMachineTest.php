<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Models\EventoTimeline;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T231 — Testes da máquina de estados de status do paciente (US-3.5).
 *
 * Cobre AC-3.5.5, 3.5.6, 3.5.8.
 */
class PacienteStatusMachineTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $adminUser;

    private User $medicoUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->adminUser] = $this->tenantAndUserForRole('clinica-alfa', 'admin-clinica');
        $this->medicoUser = $this->userForRole($this->tenant, 'medico');
    }

    private function url(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    private function criarPaciente(string $status = 'lead'): Paciente
    {
        return Paciente::factory()->forTenant($this->tenant)->create([
            'status' => $status,
        ]);
    }

    /** @test */
    public function test_transicao_lead_para_ativo_permitida_qualquer_user(): void
    {
        $paciente = $this->criarPaciente('lead');

        $response = $this->actingAs($this->medicoUser)
            ->patchJson($this->url("/pacientes/{$paciente->id}/status"), [
                'status' => 'ativo',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'ativo');
        $this->assertDatabaseHas('pacientes', ['id' => $paciente->id, 'status' => 'ativo']);
    }

    /** @test */
    public function test_transicao_ativo_para_inativo_e_vice_versa_permitida(): void
    {
        $paciente = $this->criarPaciente('ativo');

        // ativo → inativo
        $this->actingAs($this->medicoUser)
            ->patchJson($this->url("/pacientes/{$paciente->id}/status"), ['status' => 'inativo'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inativo');

        // inativo → ativo
        $this->actingAs($this->medicoUser)
            ->patchJson($this->url("/pacientes/{$paciente->id}/status"), ['status' => 'ativo'])
            ->assertOk()
            ->assertJsonPath('data.status', 'ativo');
    }

    /** @test */
    public function test_transicao_para_bloqueado_requer_admin(): void
    {
        $paciente = $this->criarPaciente('ativo');

        // Médico tenta bloquear → 403
        $this->actingAs($this->medicoUser)
            ->patchJson($this->url("/pacientes/{$paciente->id}/status"), ['status' => 'bloqueado'])
            ->assertForbidden();

        // Admin bloqueia → 200
        $this->actingAs($this->adminUser)
            ->patchJson($this->url("/pacientes/{$paciente->id}/status"), ['status' => 'bloqueado'])
            ->assertOk()
            ->assertJsonPath('data.status', 'bloqueado');
    }

    /** @test */
    public function test_transicao_bloqueado_para_ativo_requer_admin(): void
    {
        $paciente = $this->criarPaciente('bloqueado');

        // Médico tenta desbloquear → 403
        $this->actingAs($this->medicoUser)
            ->patchJson($this->url("/pacientes/{$paciente->id}/status"), ['status' => 'ativo'])
            ->assertForbidden();

        // Admin desbloqueia → 200
        $this->actingAs($this->adminUser)
            ->patchJson($this->url("/pacientes/{$paciente->id}/status"), ['status' => 'ativo'])
            ->assertOk()
            ->assertJsonPath('data.status', 'ativo');
    }

    /** @test */
    public function test_transicao_invalida_rejeitada_422(): void
    {
        $paciente = $this->criarPaciente('lead');

        // status fora do enum
        $this->actingAs($this->adminUser)
            ->patchJson($this->url("/pacientes/{$paciente->id}/status"), ['status' => 'xyz_invalido'])
            ->assertUnprocessable();

        // Combinação inválida na máquina de estados (lead não pode ir para inativo)
        $this->actingAs($this->adminUser)
            ->patchJson($this->url("/pacientes/{$paciente->id}/status"), ['status' => 'inativo'])
            ->assertUnprocessable();
    }

    /** @test */
    public function test_mudanca_status_dispara_evento_e_audit(): void
    {
        $paciente = $this->criarPaciente('lead');

        $this->actingAs($this->adminUser)
            ->patchJson($this->url("/pacientes/{$paciente->id}/status"), ['status' => 'ativo'])
            ->assertOk();

        // Audit log criado
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'paciente.status_alterado',
            'tenant_id' => $this->tenant->id,
        ]);

        // Evento na timeline criado
        $this->assertDatabaseHas('eventos_timeline', [
            'paciente_id' => $paciente->id,
            'tipo' => 'paciente.status_alterado',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function test_motivo_opcional_persistido_no_payload(): void
    {
        $paciente = $this->criarPaciente('ativo');

        $this->actingAs($this->adminUser)
            ->patchJson($this->url("/pacientes/{$paciente->id}/status"), [
                'status' => 'bloqueado',
                'motivo' => 'Pagamento atrasado',
            ])
            ->assertOk();

        $evento = EventoTimeline::where('paciente_id', $paciente->id)
            ->where('tipo', 'paciente.status_alterado')
            ->latest()
            ->first();

        $this->assertNotNull($evento);
        $this->assertEquals('Pagamento atrasado', $evento->payload['motivo'] ?? null);
    }
}
