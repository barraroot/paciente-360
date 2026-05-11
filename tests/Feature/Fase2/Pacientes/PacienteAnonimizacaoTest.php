<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T105 — Anonimização LGPD de pacientes (FR-035).
 */
class PacienteAnonimizacaoTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->adminUser] = $this->tenantAndUserForRole('clinica-anon', 'admin-clinica');
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    private function criarPaciente(array $overrides = []): int
    {
        $response = $this->postJson($this->baseUrl('/pacientes'), array_merge([
            'nome' => 'Paciente Anonimizar',
            'cpf' => '529.982.247-25',
            'telefone_primario' => '(31) 99999-0001',
            'email' => 'paciente@example.com',
            'status' => 'lead',
            'endereco' => [
                'logradouro' => 'Rua Teste',
                'numero' => '1',
                'cidade' => 'BH',
                'estado' => 'MG',
                'cep' => '30000-000',
            ],
        ], $overrides));

        $response->assertCreated();

        return $response->json('data.id');
    }

    /** @test */
    public function test_anonimizar_zera_pii_e_seta_anonimizado_em(): void
    {
        $pacienteId = $this->criarPaciente();

        $response = $this->postJson($this->baseUrl("/pacientes/{$pacienteId}/anonimizar"));
        $response->assertNoContent();

        $paciente = Paciente::withoutGlobalScope('not_anonymized')
            ->find($pacienteId);

        $this->assertNotNull($paciente);
        $this->assertNotNull($paciente->anonimizado_em);
        $this->assertNull($paciente->cpf);
        $this->assertNull($paciente->email);
        $this->assertNull($paciente->telefone_primario);
        $this->assertNull($paciente->data_nascimento);
        // endereco usa cast AsJsonArray que retorna [] para null
        $this->assertEmpty($paciente->endereco);
        $this->assertNull($paciente->documento_estrangeiro);
    }

    /** @test */
    public function test_anonimizado_nao_aparece_em_listagens(): void
    {
        $pacienteId = $this->criarPaciente(['nome' => 'Paciente Visivel']);

        // Cria outro que não será anonimizado
        $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => 'Paciente Normal',
            'telefone_primario' => '(31) 99999-0002',
            'status' => 'lead',
        ])->assertCreated();

        // Anonimiza
        $this->postJson($this->baseUrl("/pacientes/{$pacienteId}/anonimizar"))
            ->assertNoContent();

        // Lista — anonimizado não aparece
        $response = $this->getJson($this->baseUrl('/pacientes'));
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($pacienteId, $ids);
        $this->assertContains(
            'Paciente Normal',
            collect($response->json('data'))->pluck('nome')->all()
        );
    }

    /** @test */
    public function test_anonimizado_retorna_404_em_show(): void
    {
        $pacienteId = $this->criarPaciente();

        $this->postJson($this->baseUrl("/pacientes/{$pacienteId}/anonimizar"))
            ->assertNoContent();

        $response = $this->getJson($this->baseUrl("/pacientes/{$pacienteId}"));
        $response->assertNotFound();
    }

    /** @test */
    public function test_anonimizacao_audit_log_preservado(): void
    {
        $pacienteId = $this->criarPaciente();

        $this->postJson($this->baseUrl("/pacientes/{$pacienteId}/anonimizar"))
            ->assertNoContent();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'paciente.anonimizado',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function test_so_admin_clinica_pode_anonimizar(): void
    {
        $pacienteId = $this->criarPaciente();

        $medico = $this->userForRole($this->tenant, 'medico');
        $this->actingAs($medico);

        $response = $this->postJson($this->baseUrl("/pacientes/{$pacienteId}/anonimizar"));
        $response->assertForbidden();
    }
}
