<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T103 — Isolamento de dados entre tenants (AC-3.1.4).
 */
class PacienteIsolationTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenantA;

    private User $userA;

    private Tenant $tenantB;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenantA, $this->userA] = $this->tenantAndUserForRole('clinica-a-iso', 'admin-clinica');

        $this->tenantB = $this->bootstrapTenantWithRoles('clinica-b-iso');
        $this->userB = $this->userForRole($this->tenantB, 'admin-clinica');
    }

    private function criarPacienteTenant(Tenant $tenant, User $user, string $nome, ?string $cpf = null): int
    {
        $this->app->instance('tenant', $tenant);
        $this->actingAs($user);

        $payload = [
            'nome' => $nome,
            'telefone_primario' => '(31) 99999-0001',
            'status' => 'lead',
        ];

        if ($cpf !== null) {
            $payload['cpf'] = $cpf;
        }

        $response = $this->postJson($this->tenantUrl($tenant, '/pacientes'), $payload);
        $response->assertCreated();

        return $response->json('data.id');
    }

    /** @test */
    public function test_user_a_nao_ve_paciente_de_b_na_listagem(): void
    {
        $this->criarPacienteTenant($this->tenantA, $this->userA, 'Paciente Tenant A');
        $this->criarPacienteTenant($this->tenantB, $this->userB, 'Paciente Tenant B');

        // User A lista pacientes — só vê o seu
        $this->app->instance('tenant', $this->tenantA);
        $this->actingAs($this->userA);

        $response = $this->getJson($this->tenantUrl($this->tenantA, '/pacientes'));
        $response->assertOk();

        $nomes = collect($response->json('data'))->pluck('nome')->all();
        $this->assertContains('Paciente Tenant A', $nomes);
        $this->assertNotContains('Paciente Tenant B', $nomes);
    }

    /** @test */
    public function test_user_a_recebe_404_em_show_de_paciente_de_b(): void
    {
        $idPacienteB = $this->criarPacienteTenant($this->tenantB, $this->userB, 'Paciente B Secreto');

        // User A tenta acessar paciente do tenant B pelo ID
        $this->app->instance('tenant', $this->tenantA);
        $this->actingAs($this->userA);

        $response = $this->getJson($this->tenantUrl($this->tenantA, "/pacientes/{$idPacienteB}"));
        // 404 — não revela existência do paciente
        $response->assertNotFound();
    }

    /** @test */
    public function test_busca_por_cpf_em_tenant_a_nao_encontra_de_b(): void
    {
        $cpf = '529.982.247-25';

        $this->criarPacienteTenant($this->tenantB, $this->userB, 'Paciente CPF Tenant B', $cpf);

        // User A busca pelo CPF do paciente de B
        $this->app->instance('tenant', $this->tenantA);
        $this->actingAs($this->userA);

        $response = $this->getJson($this->tenantUrl($this->tenantA, '/pacientes?q=52998224725'));
        $response->assertOk();

        $data = $response->json('data');
        $this->assertEmpty($data);
    }
}
