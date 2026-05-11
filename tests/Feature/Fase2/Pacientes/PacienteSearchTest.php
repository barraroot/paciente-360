<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T106 — Busca por similaridade de pacientes (SC-011, FR-040).
 */
class PacienteSearchTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Busca por similaridade requer PostgreSQL com pg_trgm.');
        }

        $this->seedRoles();
        [$this->tenant, $this->user] = $this->tenantAndUserForRole('clinica-search', 'admin-clinica');

        // Cria 5 pacientes para busca
        $pacientes = [
            ['nome' => 'Maria José', 'telefone_primario' => '(31) 99999-1001', 'status' => 'lead'],
            ['nome' => 'Maria Jose', 'telefone_primario' => '(31) 99999-1002', 'status' => 'lead'],
            ['nome' => 'Mariah Silva', 'telefone_primario' => '(31) 99999-1003', 'status' => 'lead'],
            ['nome' => 'Marina Souza', 'telefone_primario' => '(31) 99999-1004', 'status' => 'lead'],
            ['nome' => 'Roberto Carlos', 'telefone_primario' => '(31) 99999-9999', 'status' => 'lead'],
        ];

        foreach ($pacientes as $p) {
            $this->postJson($this->tenantUrl($this->tenant, '/pacientes'), $p)
                ->assertCreated();
        }
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    /** @test */
    public function test_busca_por_nome_retorna_similares_ordenados_por_score(): void
    {
        $response = $this->getJson($this->baseUrl('/pacientes?q=maria'));
        $response->assertOk();

        $nomes = collect($response->json('data'))->pluck('nome')->all();

        // Deve retornar Maria José, Maria Jose, Mariah Silva, Marina Souza (4 de 5)
        $this->assertCount(4, $response->json('data'));
        $this->assertContains('Maria José', $nomes);
        $this->assertContains('Maria Jose', $nomes);
        $this->assertContains('Mariah Silva', $nomes);
        $this->assertContains('Marina Souza', $nomes);
        $this->assertNotContains('Roberto Carlos', $nomes);
    }

    /** @test */
    public function test_busca_por_telefone_parcial(): void
    {
        $response = $this->getJson($this->baseUrl('/pacientes?q=99999'));
        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    /** @test */
    public function test_busca_min_2_chars(): void
    {
        $response = $this->getJson($this->baseUrl('/pacientes?q=m'));
        $response->assertUnprocessable();
        $response->assertJsonValidationErrorFor('q');
    }
}
