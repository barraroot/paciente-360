<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Models\EventoTimeline;
use App\Models\FunilColuna;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T200 — Testes do Funil Kanban (US-3.4).
 *
 * Cobre AC-3.4.1 (seed lazy), AC-3.4.2 (card info), AC-3.4.3 (move persisted),
 * AC-3.4.4 (motivo perdido), AC-3.4.6 (filtros), AC-3.4.7 (funil ≠ status).
 */
class FunilKanbanTest extends TestCase
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
    public function test_get_colunas_primeira_vez_seedeia_template_padrao(): void
    {
        $this->assertDatabaseCount('funil_colunas', 0);

        $response = $this->getJson($this->baseUrl('/funil/colunas'));

        $response->assertOk();
        $response->assertJsonCount(5, 'data');

        $this->assertDatabaseCount('funil_colunas', 5);

        // Todos com is_system = true
        $this->assertEquals(5, FunilColuna::where('tenant_id', $this->tenant->id)
            ->where('is_system', true)
            ->count());

        // Apenas "perdido" tem motivo_obrigatorio = true
        $perdido = FunilColuna::where('tenant_id', $this->tenant->id)
            ->where('slug', 'perdido')
            ->first();

        $this->assertNotNull($perdido);
        $this->assertTrue($perdido->motivo_obrigatorio);

        // As demais têm motivo_obrigatorio = false
        $this->assertEquals(4, FunilColuna::where('tenant_id', $this->tenant->id)
            ->where('motivo_obrigatorio', false)
            ->count());

        // Slugs esperados
        $slugs = FunilColuna::where('tenant_id', $this->tenant->id)
            ->pluck('slug')
            ->sort()
            ->values()
            ->toArray();

        sort($slugs);
        $expected = ['agendado', 'compareceu', 'novo', 'perdido', 'qualificado'];
        $this->assertEquals($expected, $slugs);
    }

    /** @test */
    public function test_card_inclui_info_essencial(): void
    {
        // Garante que colunas existam
        $this->getJson($this->baseUrl('/funil/colunas'));

        $coluna = FunilColuna::where('tenant_id', $this->tenant->id)
            ->where('slug', 'novo')
            ->first();

        $paciente = Paciente::factory()->forTenant($this->tenant)->create([
            'nome' => 'Maria Kanban',
            'origem' => 'whatsapp',
            'funil_coluna_atual_id' => $coluna->id,
            'funil_posicao' => 1.0,
        ]);

        $response = $this->getJson($this->baseUrl("/pacientes?funil_coluna_id={$coluna->id}"));

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $paciente->id);
        $response->assertJsonPath('data.0.nome', 'Maria Kanban');
        $response->assertJsonPath('data.0.origem', 'whatsapp');
    }

    /** @test */
    public function test_move_card_persistido(): void
    {
        // Garante colunas
        $this->getJson($this->baseUrl('/funil/colunas'));

        $colunaA = FunilColuna::where('tenant_id', $this->tenant->id)->where('slug', 'novo')->first();
        $colunaB = FunilColuna::where('tenant_id', $this->tenant->id)->where('slug', 'qualificado')->first();

        $paciente = Paciente::factory()->forTenant($this->tenant)->create([
            'funil_coluna_atual_id' => $colunaA->id,
            'funil_posicao' => 1.0,
            'status' => 'ativo',
        ]);

        $response = $this->patchJson($this->baseUrl("/pacientes/{$paciente->id}/funil"), [
            'coluna_id' => $colunaB->id,
            'posicao' => 5.0,
        ]);

        $response->assertOk();

        $paciente->refresh();
        $this->assertEquals($colunaB->id, $paciente->funil_coluna_atual_id);
        $this->assertEquals(5.0, $paciente->funil_posicao);

        // AuditLog gravado
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'action' => 'lead.movido_no_funil',
        ]);

        // EventoTimeline gravado
        $this->assertDatabaseHas('eventos_timeline', [
            'tenant_id' => $this->tenant->id,
            'paciente_id' => $paciente->id,
            'tipo' => 'lead.movido_no_funil',
        ]);
    }

    /** @test */
    public function test_move_para_perdido_sem_motivo_retorna_422(): void
    {
        $this->getJson($this->baseUrl('/funil/colunas'));

        $perdido = FunilColuna::where('tenant_id', $this->tenant->id)->where('slug', 'perdido')->first();

        $paciente = Paciente::factory()->forTenant($this->tenant)->create();

        $response = $this->patchJson($this->baseUrl("/pacientes/{$paciente->id}/funil"), [
            'coluna_id' => $perdido->id,
            'posicao' => 1.0,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['motivo']);
    }

    /** @test */
    public function test_move_para_perdido_com_motivo_outro_exige_texto(): void
    {
        $this->getJson($this->baseUrl('/funil/colunas'));

        $perdido = FunilColuna::where('tenant_id', $this->tenant->id)->where('slug', 'perdido')->first();

        $paciente = Paciente::factory()->forTenant($this->tenant)->create();

        // motivo=outro sem motivo_outro -> 422
        $response = $this->patchJson($this->baseUrl("/pacientes/{$paciente->id}/funil"), [
            'coluna_id' => $perdido->id,
            'posicao' => 1.0,
            'motivo' => 'outro',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['motivo_outro']);

        // motivo=outro com motivo_outro < 10 chars -> 422
        $response2 = $this->patchJson($this->baseUrl("/pacientes/{$paciente->id}/funil"), [
            'coluna_id' => $perdido->id,
            'posicao' => 1.0,
            'motivo' => 'outro',
            'motivo_outro' => 'curto',
        ]);

        $response2->assertUnprocessable();
        $response2->assertJsonValidationErrors(['motivo_outro']);

        // motivo=outro com motivo_outro >= 10 chars -> 200
        $response3 = $this->patchJson($this->baseUrl("/pacientes/{$paciente->id}/funil"), [
            'coluna_id' => $perdido->id,
            'posicao' => 1.0,
            'motivo' => 'outro',
            'motivo_outro' => 'Motivo bem detalhado aqui',
        ]);

        $response3->assertOk();
    }

    /** @test */
    public function test_filtros_funil_aplicados(): void
    {
        $this->getJson($this->baseUrl('/funil/colunas'));

        $colunaA = FunilColuna::where('tenant_id', $this->tenant->id)->where('slug', 'novo')->first();
        $colunaB = FunilColuna::where('tenant_id', $this->tenant->id)->where('slug', 'qualificado')->first();

        Paciente::factory()->forTenant($this->tenant)->count(3)->create([
            'funil_coluna_atual_id' => $colunaA->id,
            'origem' => 'whatsapp',
        ]);

        Paciente::factory()->forTenant($this->tenant)->count(2)->create([
            'funil_coluna_atual_id' => $colunaB->id,
            'origem' => 'site',
        ]);

        // Filtro por funil_coluna_id
        $r1 = $this->getJson($this->baseUrl("/pacientes?funil_coluna_id={$colunaA->id}"));
        $r1->assertOk();
        $r1->assertJsonCount(3, 'data');

        // Filtro por origem
        $r2 = $this->getJson($this->baseUrl('/pacientes?origem=whatsapp'));
        $r2->assertOk();
        $r2->assertJsonCount(3, 'data');

        // Combinado: funil_coluna_id + origem
        $r3 = $this->getJson($this->baseUrl("/pacientes?funil_coluna_id={$colunaB->id}&origem=site"));
        $r3->assertOk();
        $r3->assertJsonCount(2, 'data');
    }

    /** @test */
    public function test_funil_e_status_sao_dimensoes_independentes(): void
    {
        $this->getJson($this->baseUrl('/funil/colunas'));

        $colunaA = FunilColuna::where('tenant_id', $this->tenant->id)->where('slug', 'novo')->first();
        $colunaB = FunilColuna::where('tenant_id', $this->tenant->id)->where('slug', 'qualificado')->first();

        $paciente = Paciente::factory()->forTenant($this->tenant)->create([
            'status' => 'ativo',
            'funil_coluna_atual_id' => $colunaA->id,
        ]);

        $this->patchJson($this->baseUrl("/pacientes/{$paciente->id}/funil"), [
            'coluna_id' => $colunaB->id,
            'posicao' => 1.0,
        ])->assertOk();

        $paciente->refresh();
        $this->assertEquals($colunaB->id, $paciente->funil_coluna_atual_id);

        // Status permanece ativo — funil e status são dimensões independentes
        $this->assertEquals('ativo', $paciente->status);
    }
}
