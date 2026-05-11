<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T101 — Validação de CPF no cadastro de paciente (AC-3.1.2).
 */
class PacienteCpfValidationTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->user] = $this->tenantAndUserForRole('clinica-cpf', 'admin-clinica');
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    /** @test */
    public function test_cpf_dv_invalido_retorna_422(): void
    {
        $response = $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => 'Paciente CPF Inválido',
            'cpf' => '111.111.111-12',
            'telefone_primario' => '(31) 99999-0001',
            'status' => 'lead',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrorFor('cpf');
    }

    /** @test */
    public function test_cpf_ausente_aceito(): void
    {
        $response = $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => 'Paciente Sem CPF',
            'telefone_primario' => '(31) 99999-0002',
            'status' => 'lead',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('pacientes', [
            'nome' => 'Paciente Sem CPF',
            'cpf' => null,
        ]);
    }

    /** @test */
    public function test_documento_estrangeiro_aceito_sem_cpf(): void
    {
        $response = $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => 'Paciente Estrangeiro',
            'documento_estrangeiro' => 'AB123456',
            'telefone_primario' => '(31) 99999-0003',
            'status' => 'lead',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('pacientes', [
            'nome' => 'Paciente Estrangeiro',
            'documento_estrangeiro' => 'AB123456',
            'cpf' => null,
        ]);
    }

    /** @test */
    public function test_cpf_aceita_com_ou_sem_mascara(): void
    {
        // CPF com máscara
        $response = $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => 'Paciente CPF Com Máscara',
            'cpf' => '123.456.789-09',
            'telefone_primario' => '(31) 99999-0004',
            'status' => 'lead',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('pacientes', ['cpf' => '12345678909']);

        // CPF sem máscara — mesmo CPF causaria duplicata, usar outro
        $response2 = $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => 'Paciente CPF Sem Máscara',
            'cpf' => '529.982.247-25',
            'telefone_primario' => '(31) 99999-0005',
            'status' => 'lead',
        ]);

        $response2->assertCreated();
        $this->assertDatabaseHas('pacientes', ['cpf' => '52998224725']);

        // Sem máscara explícita
        $response3 = $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => 'Paciente CPF Raw',
            'cpf' => '11144477735',
            'telefone_primario' => '(31) 99999-0006',
            'status' => 'lead',
        ]);

        $response3->assertCreated();
        $this->assertDatabaseHas('pacientes', ['cpf' => '11144477735']);
    }
}
