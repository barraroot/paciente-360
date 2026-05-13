<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T173 — Testes de permissão para importação de pacientes (AC-3.3.9).
 */
class ImportacaoPermissionTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('imports');
        Bus::fake();
        $this->seedRoles();
        $this->tenant = $this->bootstrapTenantWithRoles('clinica-perms');
        $this->app->instance('tenant', $this->tenant);
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    private function buildMinimalCsv(): UploadedFile
    {
        $csv = "nome,cpf,email\nJoao Silva,,joao@ex.com\n";

        return UploadedFile::fake()->createWithContent('p.csv', $csv);
    }

    /** @test */
    public function test_admin_clinica_pode_importar(): void
    {
        $user = $this->userForRole($this->tenant, 'admin-clinica');
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson($this->baseUrl('/pacientes/importacao'), [
            'arquivo' => $this->buildMinimalCsv(),
            'status_inicial' => 'lead',
        ]);

        $response->assertStatus(202);
    }

    /** @test */
    public function test_medico_nao_pode_importar(): void
    {
        $user = $this->userForRole($this->tenant, 'medico');
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson($this->baseUrl('/pacientes/importacao'), [
            'arquivo' => $this->buildMinimalCsv(),
            'status_inicial' => 'lead',
        ]);

        $response->assertForbidden();
    }

    /** @test */
    public function test_atendente_nao_pode_importar(): void
    {
        $user = $this->userForRole($this->tenant, 'atendente');
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson($this->baseUrl('/pacientes/importacao'), [
            'arquivo' => $this->buildMinimalCsv(),
            'status_inicial' => 'lead',
        ]);

        $response->assertForbidden();
    }

    /** @test */
    public function test_recepcionista_nao_pode_importar(): void
    {
        $user = $this->userForRole($this->tenant, 'recepcionista');
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson($this->baseUrl('/pacientes/importacao'), [
            'arquivo' => $this->buildMinimalCsv(),
            'status_inicial' => 'lead',
        ]);

        $response->assertForbidden();
    }
}
