<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Models\AuditLog;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T100 — Testes de cadastro manual de paciente (US-3.1).
 *
 * Cobre AC-3.1.1, AC-3.1.5, AC-3.1.6, AC-3.1.7, AC-3.1.8.
 */
class PacienteCadastroTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->user] = $this->tenantAndUserForRole('clinica-alfa', 'admin-clinica');
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    /** @test */
    public function test_cadastro_minimo_bem_sucedido(): void
    {
        $response = $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => 'João Silva',
            'telefone_primario' => '(31) 99999-1111',
            'status' => 'lead',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.nome', 'João Silva');
        $response->assertJsonPath('data.status', 'lead');
        $response->assertJsonStructure(['data' => ['id', 'nome', 'status', 'created_at']]);

        $this->assertDatabaseHas('pacientes', [
            'nome' => 'João Silva',
            'status' => 'lead',
            'tenant_id' => $this->tenant->id,
        ]);

        $paciente = Paciente::where('nome', 'João Silva')->first();
        $this->assertNotNull($paciente);

        // Audit log criado
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'paciente.criado',
            'tenant_id' => $this->tenant->id,
        ]);

        // Evento timeline criado
        $this->assertDatabaseHas('eventos_timeline', [
            'paciente_id' => $paciente->id,
            'tipo' => 'paciente.criado',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function test_cadastro_completo_com_todos_campos(): void
    {
        $response = $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => 'Maria Aparecida Santos',
            'cpf' => '529.982.247-25',
            'data_nascimento' => '1985-03-15',
            'telefone_primario' => '(11) 98888-2222',
            'email' => 'maria@example.com',
            'status' => 'ativo',
            'origem' => 'indicacao',
            'origem_detalhe' => 'Indicada pelo Dr. Carlos',
            'endereco' => [
                'logradouro' => 'Rua das Flores',
                'numero' => '100',
                'cidade' => 'São Paulo',
                'estado' => 'SP',
                'cep' => '01310-100',
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.nome', 'Maria Aparecida Santos');
        $response->assertJsonPath('data.status', 'ativo');
        $response->assertJsonPath('data.origem', 'indicacao');

        $this->assertDatabaseHas('pacientes', [
            'nome' => 'Maria Aparecida Santos',
            'cpf' => '52998224725',
            'email' => 'maria@example.com',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function test_status_inicial_aceita_lead_ou_ativo(): void
    {
        foreach (['lead', 'ativo'] as $status) {
            $response = $this->postJson($this->baseUrl('/pacientes'), [
                'nome' => "Paciente Status {$status}",
                'telefone_primario' => '(31) 99999-0001',
                'status' => $status,
            ]);

            $response->assertCreated();
            $response->assertJsonPath('data.status', $status);
        }
    }

    /** @test */
    public function test_status_inicial_rejeita_inativo_ou_bloqueado(): void
    {
        foreach (['inativo', 'bloqueado'] as $status) {
            $response = $this->postJson($this->baseUrl('/pacientes'), [
                'nome' => "Paciente Status {$status}",
                'telefone_primario' => '(31) 99999-0002',
                'status' => $status,
            ]);

            $response->assertUnprocessable();
        }
    }

    /** @test */
    public function test_profissional_responsavel_associado(): void
    {
        $profissional = Professional::factory()->forTenant($this->tenant)->create();

        $response = $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => 'Paciente Com Profissional',
            'telefone_primario' => '(31) 99999-3333',
            'status' => 'lead',
            'profissional_responsavel_id' => $profissional->id,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('pacientes', [
            'nome' => 'Paciente Com Profissional',
            'profissional_responsavel_id' => $profissional->id,
        ]);
    }

    /** @test */
    public function test_origem_enum_aceita_valores_do_data_model(): void
    {
        $origensValidas = ['site', 'indicacao', 'whatsapp', 'instagram', 'telefone', 'presencial', 'outro'];

        foreach ($origensValidas as $origem) {
            $response = $this->postJson($this->baseUrl('/pacientes'), [
                'nome' => "Paciente {$origem}",
                'telefone_primario' => '(31) 99999-0003',
                'status' => 'lead',
                'origem' => $origem,
            ]);

            $response->assertCreated("Origem '{$origem}' deveria ser aceita mas retornou {$response->status()}");
        }

        // Valor inválido → 422
        $response = $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => 'Paciente Origem Invalida',
            'telefone_primario' => '(31) 99999-0004',
            'status' => 'lead',
            'origem' => 'invalida',
        ]);

        $response->assertUnprocessable();
    }

    /** @test */
    public function test_origem_origem_default_manual(): void
    {
        $response = $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => 'Paciente Sem Origem Origem',
            'telefone_primario' => '(31) 99999-0005',
            'status' => 'lead',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('pacientes', [
            'nome' => 'Paciente Sem Origem Origem',
            'origem_origem' => 'manual',
        ]);
    }

    /** @test */
    public function test_evento_paciente_criado_registrado_em_audit_e_timeline(): void
    {
        $response = $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => 'Paciente Audit Timeline',
            'telefone_primario' => '(31) 99999-0006',
            'status' => 'lead',
        ]);

        $response->assertCreated();

        $pacienteId = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'paciente.criado',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertDatabaseHas('eventos_timeline', [
            'paciente_id' => $pacienteId,
            'tipo' => 'paciente.criado',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function test_payload_audit_mascara_cpf(): void
    {
        $response = $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => 'Paciente CPF Mascarado',
            'cpf' => '529.982.247-25',
            'telefone_primario' => '(31) 99999-0007',
            'status' => 'lead',
        ]);

        $response->assertCreated();

        $auditLog = AuditLog::where('action', 'paciente.criado')
            ->where('tenant_id', $this->tenant->id)
            ->latest()
            ->first();

        $this->assertNotNull($auditLog);

        $payload = $auditLog->payload;

        // CPF nunca aparece em claro no audit_log
        $this->assertNotEquals('52998224725', $payload['cpf'] ?? null);
        // Formato mascarado correto
        $this->assertEquals('***.***.***-25', $payload['cpf'] ?? null);
    }
}
