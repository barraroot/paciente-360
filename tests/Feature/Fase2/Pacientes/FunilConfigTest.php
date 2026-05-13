<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Models\FunilColuna;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T201 — Testes de configuração de colunas do Funil (US-3.4).
 *
 * Admin renomeia/reordena colunas; médico não pode configurar.
 */
class FunilConfigTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->adminUser] = $this->tenantAndUserForRole('clinica-alfa', 'admin-clinica');

        // Seed as colunas via GET
        $this->getJson($this->tenantUrl($this->tenant, '/funil/colunas'));
    }

    /** @test */
    public function test_admin_renomeia_coluna_preservando_slug(): void
    {
        $coluna = FunilColuna::where('tenant_id', $this->tenant->id)
            ->where('slug', 'novo')
            ->first();

        $response = $this->patchJson(
            $this->tenantUrl($this->tenant, "/funil/colunas/{$coluna->id}"),
            ['nome' => 'Convertido']
        );

        $response->assertOk();
        $response->assertJsonPath('data.nome', 'Convertido');
        $response->assertJsonPath('data.slug', 'novo'); // slug preservado

        $coluna->refresh();
        $this->assertEquals('Convertido', $coluna->nome);
        $this->assertEquals('novo', $coluna->slug);
    }

    /** @test */
    public function test_admin_marca_motivo_obrigatorio(): void
    {
        $coluna = FunilColuna::where('tenant_id', $this->tenant->id)
            ->where('slug', 'qualificado')
            ->first();

        $this->assertFalse($coluna->motivo_obrigatorio);

        $response = $this->patchJson(
            $this->tenantUrl($this->tenant, "/funil/colunas/{$coluna->id}"),
            ['motivo_obrigatorio' => true]
        );

        $response->assertOk();

        $coluna->refresh();
        $this->assertTrue($coluna->motivo_obrigatorio);
    }

    /** @test */
    public function test_admin_reordena_via_posicao_colisao_retorna_409(): void
    {
        $colunaA = FunilColuna::where('tenant_id', $this->tenant->id)->where('slug', 'novo')->first();
        $colunaB = FunilColuna::where('tenant_id', $this->tenant->id)->where('slug', 'qualificado')->first();

        // Tentar definir posicao da coluna A para o mesmo valor da coluna B → 409
        $response = $this->patchJson(
            $this->tenantUrl($this->tenant, "/funil/colunas/{$colunaA->id}"),
            ['posicao' => $colunaB->posicao]
        );

        $response->assertStatus(409);
    }

    /** @test */
    public function test_medico_nao_pode_configurar_coluna(): void
    {
        $medico = $this->userForRole($this->tenant, 'medico');
        Sanctum::actingAs($medico, ['*']);

        $coluna = FunilColuna::where('tenant_id', $this->tenant->id)->where('slug', 'novo')->first();

        $response = $this->patchJson(
            $this->tenantUrl($this->tenant, "/funil/colunas/{$coluna->id}"),
            ['nome' => 'Tentativa']
        );

        $response->assertForbidden();
    }
}
