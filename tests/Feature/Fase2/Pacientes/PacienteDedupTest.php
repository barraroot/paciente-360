<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T102 — Deduplicação de paciente no cadastro (AC-3.1.3).
 *
 * Decisão de implementação: `ignorar_duplicata=true` desativa a checagem
 * de dedup mas NÃO permite criar paciente com mesmo CPF (constraint UNIQUE
 * (cpf, tenant_id) no banco). Retorna 422 explicando que o CPF já existe.
 * Para criar paralelo, o CPF deve estar nulo em um dos pacientes.
 */
class PacienteDedupTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->user] = $this->tenantAndUserForRole('clinica-dedup', 'admin-clinica');
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    /** @test */
    public function test_cpf_duplicado_retorna_409_com_sugestao(): void
    {
        // Cria paciente A com CPF
        $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => 'Paciente A',
            'cpf' => '529.982.247-25',
            'telefone_primario' => '(31) 99999-0001',
            'status' => 'lead',
        ])->assertCreated();

        // Tenta criar paciente B com mesmo CPF
        $response = $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => 'Paciente B',
            'cpf' => '529.982.247-25',
            'telefone_primario' => '(31) 99999-0002',
            'status' => 'lead',
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'dedup_conflict');
        $response->assertJsonStructure([
            'error',
            'message',
            'candidatos' => [['id', 'nome', 'cpf', 'status']],
            'acoes',
        ]);

        // Candidato correto retornado
        $candidatos = $response->json('candidatos');
        $this->assertCount(1, $candidatos);
        $this->assertEquals('Paciente A', $candidatos[0]['nome']);
    }

    /** @test */
    public function test_ignorar_duplicata_true_com_mesmo_cpf_retorna_422(): void
    {
        // Cria paciente A com CPF
        $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => 'Paciente A',
            'cpf' => '529.982.247-25',
            'telefone_primario' => '(31) 99999-0001',
            'status' => 'lead',
        ])->assertCreated();

        // Tenta criar com mesmo CPF + ignorar_duplicata=true
        // Decisão: NÃO é possível criar paralelo com mesmo CPF (UNIQUE constraint).
        // Backend retorna 422 explicando a limitação.
        $response = $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => 'Paciente B',
            'cpf' => '529.982.247-25',
            'telefone_primario' => '(31) 99999-0002',
            'status' => 'lead',
            'ignorar_duplicata' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'cpf_duplicado_nao_permitido');
    }

    /** @test */
    public function test_telefone_duplicado_nao_dispara_dedup(): void
    {
        // Cria paciente A com telefone
        $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => 'Paciente A Telefone',
            'telefone_primario' => '(31) 99999-0001',
            'status' => 'lead',
        ])->assertCreated();

        // Cria paciente B com mesmo telefone — deve ser 201 (telefone não é único)
        $response = $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => 'Paciente B Telefone',
            'telefone_primario' => '(31) 99999-0001',
            'status' => 'lead',
        ]);

        $response->assertCreated();
    }

    /** @test */
    public function test_dedup_so_busca_no_tenant_atual(): void
    {
        $this->seedRoles();
        $tenantB = $this->bootstrapTenantWithRoles('clinica-beta-dedup');

        // Cria paciente no tenant atual (clinica-dedup) com CPF X
        $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => 'Paciente Tenant A',
            'cpf' => '529.982.247-25',
            'telefone_primario' => '(31) 99999-0001',
            'status' => 'lead',
        ])->assertCreated();

        // Cria paciente no tenant B com mesmo CPF → 201 (cross-tenant não dispara dedup)
        $userB = $this->userForRole($tenantB, 'admin-clinica');
        $this->app->instance('tenant', $tenantB);
        Sanctum::actingAs($userB, ['*']);

        $response = $this->postJson($this->tenantUrl($tenantB, '/pacientes'), [
            'nome' => 'Paciente Tenant B',
            'cpf' => '529.982.247-25',
            'telefone_primario' => '(31) 99999-0002',
            'status' => 'lead',
        ]);

        $response->assertCreated();
    }
}
