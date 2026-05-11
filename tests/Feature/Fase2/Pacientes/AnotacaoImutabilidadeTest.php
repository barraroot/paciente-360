<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Exceptions\Pacientes\AnotacaoImutavelException;
use App\Models\Anotacao;
use App\Models\EventoTimeline;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T142 — Testes de imutabilidade de anotações e retratação (US-3.2).
 *
 * Cobre AC-3.2.5.
 */
class AnotacaoImutabilidadeTest extends TestCase
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

    /** @test */
    public function test_anotacao_update_via_model_lanca_excecao(): void
    {
        $anotacao = Anotacao::factory()
            ->geral()
            ->comAutor($this->adminClinica)
            ->create([
                'tenant_id' => $this->tenant->id,
                'paciente_id' => $this->paciente->id,
            ]);

        $this->expectException(AnotacaoImutavelException::class);
        $anotacao->update(['texto' => 'Texto alterado ilegalmente']);
    }

    /** @test */
    public function test_anotacao_delete_via_model_lanca_excecao(): void
    {
        $anotacao = Anotacao::factory()
            ->geral()
            ->comAutor($this->adminClinica)
            ->create([
                'tenant_id' => $this->tenant->id,
                'paciente_id' => $this->paciente->id,
            ]);

        $this->expectException(AnotacaoImutavelException::class);
        $anotacao->delete();
    }

    /** @test */
    public function test_post_retratacao_cria_nova_anotacao_linkada(): void
    {
        $anotacao = Anotacao::factory()
            ->geral()
            ->comAutor($this->adminClinica)
            ->create([
                'tenant_id' => $this->tenant->id,
                'paciente_id' => $this->paciente->id,
                'texto' => 'Anotação original.',
            ]);

        $response = $this->postJson(
            $this->baseUrl("/pacientes/{$this->paciente->id}/anotacoes/{$anotacao->id}/retratacao"),
            ['texto' => 'Correção da anotação original com detalhes adicionais.'],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.retratacao_de_anotacao_id', $anotacao->id);
        $response->assertJsonPath('data.eh_retratacao', true);

        // Original permanece intacta
        $this->assertDatabaseHas('anotacoes', [
            'id' => $anotacao->id,
            'texto' => 'Anotação original.',
        ]);
    }

    /** @test */
    public function test_retratacao_aparece_como_evento_separado_na_timeline(): void
    {
        $anotacao = Anotacao::factory()
            ->geral()
            ->comAutor($this->adminClinica)
            ->create([
                'tenant_id' => $this->tenant->id,
                'paciente_id' => $this->paciente->id,
                'texto' => 'Anotação original para retratar.',
            ]);

        $eventosAntes = EventoTimeline::where('paciente_id', $this->paciente->id)->count();

        $this->postJson(
            $this->baseUrl("/pacientes/{$this->paciente->id}/anotacoes/{$anotacao->id}/retratacao"),
            ['texto' => 'Retratação com texto explicativo completo.'],
        )->assertCreated();

        // Verifica que evento de retratação foi criado na timeline
        $this->assertDatabaseHas('eventos_timeline', [
            'paciente_id' => $this->paciente->id,
            'tipo' => 'paciente.anotacao_retratada',
        ]);
    }

    /** @test */
    public function test_retratacao_min_10_chars(): void
    {
        $anotacao = Anotacao::factory()
            ->geral()
            ->comAutor($this->adminClinica)
            ->create([
                'tenant_id' => $this->tenant->id,
                'paciente_id' => $this->paciente->id,
            ]);

        $response = $this->postJson(
            $this->baseUrl("/pacientes/{$this->paciente->id}/anotacoes/{$anotacao->id}/retratacao"),
            ['texto' => 'Curto'],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['texto']);
    }

    /** @test */
    public function test_nao_pode_retratar_uma_retratacao(): void
    {
        $original = Anotacao::factory()
            ->geral()
            ->comAutor($this->adminClinica)
            ->create([
                'tenant_id' => $this->tenant->id,
                'paciente_id' => $this->paciente->id,
                'texto' => 'Anotação original.',
            ]);

        // Cria retratação via API
        $retratacaoResponse = $this->postJson(
            $this->baseUrl("/pacientes/{$this->paciente->id}/anotacoes/{$original->id}/retratacao"),
            ['texto' => 'Retratação da anotação original com detalhes.'],
        );
        $retratacaoResponse->assertCreated();

        $retratacaoId = $retratacaoResponse->json('data.id');

        // Tentar retratar a retratação deve retornar 422
        $response = $this->postJson(
            $this->baseUrl("/pacientes/{$this->paciente->id}/anotacoes/{$retratacaoId}/retratacao"),
            ['texto' => 'Tentativa de retratar uma retratação, deve falhar.'],
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'anotacao_ja_eh_retratacao');
    }
}
