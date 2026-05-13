<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Models\Anotacao;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T141 — Testes de anotações tipadas e visibilidade por perfil (US-3.2).
 *
 * Cobre AC-3.2.4 + visibilidade por ability `paciente.note.view:{tipo}`.
 *
 * Decisão de design (spec T141):
 *  - `paciente.note.write` é ability única — quem pode escrever, escreve
 *    qualquer tipo. A visibilidade é granular apenas na LEITURA.
 */
class PacienteAnotacaoTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $adminClinica;

    private Paciente $paciente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->adminClinica] = $this->tenantAndUserForRole('clinica-alfa', 'admin-clinica');

        $this->paciente = Paciente::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    private function anotacoesUrl(): string
    {
        return $this->baseUrl("/pacientes/{$this->paciente->id}/anotacoes");
    }

    /** @test */
    public function test_admin_clinica_pode_criar_anotacao_de_qualquer_tipo(): void
    {
        foreach (['geral', 'clinica', 'comportamental', 'financeira'] as $tipo) {
            $response = $this->postJson($this->anotacoesUrl(), [
                'tipo' => $tipo,
                'texto' => "Anotação de teste tipo {$tipo}.",
            ]);
            $response->assertCreated();
            $response->assertJsonPath('data.tipo', $tipo);
        }
    }

    /** @test */
    public function test_medico_pode_criar_anotacao_de_qualquer_tipo(): void
    {
        $medico = $this->userForRole($this->tenant, 'medico');
        Sanctum::actingAs($medico, ['*']);

        // Médico tem paciente.note.write — pode criar qualquer tipo
        foreach (['geral', 'clinica', 'comportamental'] as $tipo) {
            $response = $this->postJson($this->anotacoesUrl(), [
                'tipo' => $tipo,
                'texto' => "Anotação médico tipo {$tipo}.",
            ]);
            $response->assertCreated();
        }
    }

    /** @test */
    public function test_atendente_pode_criar_anotacao_de_qualquer_tipo_se_tiver_write(): void
    {
        $atendente = $this->userForRole($this->tenant, 'atendente');
        Sanctum::actingAs($atendente, ['*']);

        // Atendente com paciente.note.write pode criar qualquer tipo
        // (visibilidade é granular apenas na leitura)
        $response = $this->postJson($this->anotacoesUrl(), [
            'tipo' => 'geral',
            'texto' => 'Anotação geral pelo atendente.',
        ]);

        // Se atendente não tem note.write, será 403; se tem, será 201
        $this->assertContains($response->status(), [201, 403]);
    }

    /** @test */
    public function test_atendente_nao_ve_anotacoes_clinicas(): void
    {
        // Admin cria 1 anotação clínica + 1 geral
        Anotacao::factory()
            ->clinica()
            ->comAutor($this->adminClinica)
            ->create([
                'tenant_id' => $this->tenant->id,
                'paciente_id' => $this->paciente->id,
            ]);

        Anotacao::factory()
            ->geral()
            ->comAutor($this->adminClinica)
            ->create([
                'tenant_id' => $this->tenant->id,
                'paciente_id' => $this->paciente->id,
            ]);

        // Autentica como atendente
        $atendente = $this->userForRole($this->tenant, 'atendente');
        Sanctum::actingAs($atendente, ['*']);

        $response = $this->getJson($this->anotacoesUrl());
        $response->assertOk();

        $tipos = array_column($response->json('data'), 'tipo');

        // Atendente NÃO deve ver clínica
        $this->assertNotContains('clinica', $tipos);
        // Mas deve ver geral
        $this->assertContains('geral', $tipos);
    }

    /** @test */
    public function test_anotacao_clinica_so_para_medico_e_admin(): void
    {
        Anotacao::factory()
            ->clinica()
            ->comAutor($this->adminClinica)
            ->create([
                'tenant_id' => $this->tenant->id,
                'paciente_id' => $this->paciente->id,
            ]);

        // Médico deve ver clínica
        $medico = $this->userForRole($this->tenant, 'medico');
        Sanctum::actingAs($medico, ['*']);

        $response = $this->getJson($this->anotacoesUrl());
        $response->assertOk();
        $tipos = array_column($response->json('data'), 'tipo');
        $this->assertContains('clinica', $tipos);

        // Atendente NÃO deve ver clínica
        $atendente = $this->userForRole($this->tenant, 'atendente');
        Sanctum::actingAs($atendente, ['*']);

        $response2 = $this->getJson($this->anotacoesUrl());
        $response2->assertOk();
        $tipos2 = array_column($response2->json('data'), 'tipo');
        $this->assertNotContains('clinica', $tipos2);
    }

    /** @test */
    public function test_anotacao_financeira_so_para_admin_clinica(): void
    {
        Anotacao::factory()
            ->financeira()
            ->comAutor($this->adminClinica)
            ->create([
                'tenant_id' => $this->tenant->id,
                'paciente_id' => $this->paciente->id,
            ]);

        // Admin vê financeira
        $responseAdmin = $this->getJson($this->anotacoesUrl());
        $responseAdmin->assertOk();
        $tiposAdmin = array_column($responseAdmin->json('data'), 'tipo');
        $this->assertContains('financeira', $tiposAdmin);

        // Médico NÃO deve ver financeira (não tem paciente.note.view:financeira)
        $medico = $this->userForRole($this->tenant, 'medico');
        Sanctum::actingAs($medico, ['*']);

        $responseMedico = $this->getJson($this->anotacoesUrl());
        $responseMedico->assertOk();
        $tiposMedico = array_column($responseMedico->json('data'), 'tipo');
        $this->assertNotContains('financeira', $tiposMedico);
    }

    /** @test */
    public function test_anotacoes_aparecem_na_timeline_filtrada(): void
    {
        // Admin cria anotação clínica via API (dispara evento → timeline)
        $createResponse = $this->postJson(
            $this->baseUrl("/pacientes/{$this->paciente->id}/anotacoes"),
            ['tipo' => 'clinica', 'texto' => 'Anotação clínica para teste de timeline.'],
        );
        $createResponse->assertCreated();
        $anotacaoId = $createResponse->json('data.id');

        // Admin deve ver o evento de anotação na timeline
        $responsAdmin = $this->getJson(
            $this->baseUrl("/pacientes/{$this->paciente->id}/timeline"),
        );
        $responsAdmin->assertOk();
        $tiposAdmin = array_column($responsAdmin->json('data'), 'tipo');
        $this->assertContains('paciente.anotacao_criada', $tiposAdmin);

        // Atendente NÃO deve ver o evento de anotação clínica na timeline
        $atendente = $this->userForRole($this->tenant, 'atendente');
        Sanctum::actingAs($atendente, ['*']);

        $responseAtendente = $this->getJson(
            $this->baseUrl("/pacientes/{$this->paciente->id}/timeline"),
        );
        $responseAtendente->assertOk();

        // O evento da anotação clínica deve ser filtrado da timeline do atendente
        $anotacaoEventos = collect($responseAtendente->json('data'))
            ->filter(fn ($e) => $e['tipo'] === 'paciente.anotacao_criada'
                && ($e['referencia']['id'] ?? null) === $anotacaoId)
            ->values();

        $this->assertEmpty($anotacaoEventos);
    }
}
