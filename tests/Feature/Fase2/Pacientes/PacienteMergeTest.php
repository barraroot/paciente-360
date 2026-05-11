<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Models\Anotacao;
use App\Models\MesclagemPaciente;
use App\Models\Paciente;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T104 — Mesclagem reversível de pacientes (US-3.1).
 */
class PacienteMergeTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->user] = $this->tenantAndUserForRole('clinica-merge', 'admin-clinica');
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    private function criarPaciente(string $nome, ?string $cpf = null): Paciente
    {
        $response = $this->postJson($this->baseUrl('/pacientes'), [
            'nome' => $nome,
            'cpf' => $cpf,
            'telefone_primario' => '(31) 99999-0001',
            'status' => 'lead',
        ]);

        $response->assertCreated();

        return Paciente::find($response->json('data.id'));
    }

    /** @test */
    public function test_merge_executado_marca_origem_como_merged_into(): void
    {
        $alvo = $this->criarPaciente('Paciente Alvo');
        $origem = $this->criarPaciente('Paciente Origem');

        $response = $this->postJson($this->baseUrl('/pacientes/mesclagens'), [
            'paciente_alvo_id' => $alvo->id,
            'pacientes_origem_ids' => [$origem->id],
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['data' => ['id', 'paciente_alvo_id', 'executada_em', 'reversivel_ate']]);

        // Origem marcada como merged
        $origem->refresh();
        $this->assertEquals($alvo->id, $origem->merged_into_paciente_id);
        $this->assertNotNull($origem->merged_at);
    }

    /** @test */
    public function test_merge_move_tags_anotacoes_eventos_para_alvo(): void
    {
        $alvo = $this->criarPaciente('Paciente Alvo Tags');
        $origem = $this->criarPaciente('Paciente Origem Tags');

        // Adiciona 2 tags e 1 anotação no paciente origem
        $tag1 = Tag::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Tag Um',
            'nome_normalizado' => 'tag um',
            'tipo' => 'livre',
        ]);

        $tag2 = Tag::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Tag Dois',
            'nome_normalizado' => 'tag dois',
            'tipo' => 'livre',
        ]);

        $origem->tags()->attach([$tag1->id, $tag2->id], ['tenant_id' => $this->tenant->id, 'aplicada_por_user_id' => $this->user->id, 'aplicada_at' => now()]);

        Anotacao::create([
            'tenant_id' => $this->tenant->id,
            'paciente_id' => $origem->id,
            'autor_id' => $this->user->id,
            'tipo' => 'geral',
            'texto' => 'Anotação de teste',
        ]);

        $response = $this->postJson($this->baseUrl('/pacientes/mesclagens'), [
            'paciente_alvo_id' => $alvo->id,
            'pacientes_origem_ids' => [$origem->id],
        ]);

        $response->assertCreated();

        // Alvo deve ter as 2 tags
        $alvo->refresh();
        $this->assertEquals(2, $alvo->tags()->count());

        // Alvo deve ter a anotação
        $this->assertEquals(1, $alvo->anotacoes()->count());
    }

    /** @test */
    public function test_merge_snapshot_pre_merge_persistido(): void
    {
        $alvo = $this->criarPaciente('Paciente Alvo Snapshot');
        $origem = $this->criarPaciente('Paciente Origem Snapshot');

        $response = $this->postJson($this->baseUrl('/pacientes/mesclagens'), [
            'paciente_alvo_id' => $alvo->id,
            'pacientes_origem_ids' => [$origem->id],
        ]);

        $response->assertCreated();

        $mesclagemId = $response->json('data.id');
        $mesclagem = MesclagemPaciente::find($mesclagemId);

        $this->assertNotNull($mesclagem->snapshot_pre_merge);
        $this->assertArrayHasKey('alvo', $mesclagem->snapshot_pre_merge);
        $this->assertArrayHasKey('origens', $mesclagem->snapshot_pre_merge);
    }

    /** @test */
    public function test_merge_dispara_evento_paciente_mesclado(): void
    {
        $alvo = $this->criarPaciente('Paciente Alvo Evento');
        $origem = $this->criarPaciente('Paciente Origem Evento');

        $response = $this->postJson($this->baseUrl('/pacientes/mesclagens'), [
            'paciente_alvo_id' => $alvo->id,
            'pacientes_origem_ids' => [$origem->id],
        ]);

        $response->assertCreated();

        // Audit log gerado
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'paciente.mesclado',
            'tenant_id' => $this->tenant->id,
        ]);

        // Timeline do alvo atualizada
        $this->assertDatabaseHas('eventos_timeline', [
            'paciente_id' => $alvo->id,
            'tipo' => 'paciente.mesclado',
        ]);
    }

    /** @test */
    public function test_reverter_dentro_de_30_dias_restaura_estado(): void
    {
        $alvo = $this->criarPaciente('Paciente Alvo Reverter');
        $origem = $this->criarPaciente('Paciente Origem Reverter');

        // Adiciona tag e anotação na origem antes do merge
        $tag = Tag::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Tag Reverter',
            'nome_normalizado' => 'tag reverter',
            'tipo' => 'livre',
        ]);

        $origem->tags()->attach($tag->id, ['tenant_id' => $this->tenant->id, 'aplicada_por_user_id' => $this->user->id, 'aplicada_at' => now()]);

        Anotacao::create([
            'tenant_id' => $this->tenant->id,
            'paciente_id' => $origem->id,
            'autor_id' => $this->user->id,
            'tipo' => 'geral',
            'texto' => 'Anotação pré-merge',
        ]);

        $mergeResponse = $this->postJson($this->baseUrl('/pacientes/mesclagens'), [
            'paciente_alvo_id' => $alvo->id,
            'pacientes_origem_ids' => [$origem->id],
        ]);

        $mergeResponse->assertCreated();
        $mesclagemId = $mergeResponse->json('data.id');

        // Reverte dentro do prazo
        $response = $this->postJson($this->baseUrl("/pacientes/mesclagens/{$mesclagemId}/reverter"));
        $response->assertNoContent();

        // Origem volta ao estado normal
        $origem->refresh();
        $this->assertNull($origem->merged_into_paciente_id);
        $this->assertNull($origem->merged_at);

        // Tags restauradas na origem (eventos_timeline são imutáveis — ficam no snapshot)
        $this->assertEquals(1, $origem->tags()->count());
        // Anotação restaurada na origem
        $this->assertEquals(1, $origem->anotacoes()->count());
    }

    /** @test */
    public function test_reverter_fora_de_30_dias_retorna_410(): void
    {
        $alvo = $this->criarPaciente('Paciente Alvo Expirado');
        $origem = $this->criarPaciente('Paciente Origem Expirado');

        $mergeResponse = $this->postJson($this->baseUrl('/pacientes/mesclagens'), [
            'paciente_alvo_id' => $alvo->id,
            'pacientes_origem_ids' => [$origem->id],
        ]);

        $mergeResponse->assertCreated();
        $mesclagemId = $mergeResponse->json('data.id');

        // Manipula reversivel_ate para o passado
        MesclagemPaciente::where('id', $mesclagemId)->update([
            'reversivel_ate' => now()->subDay(),
        ]);

        $response = $this->postJson($this->baseUrl("/pacientes/mesclagens/{$mesclagemId}/reverter"));
        $response->assertStatus(410);
    }

    /** @test */
    public function test_reverter_ja_revertida_retorna_422(): void
    {
        $alvo = $this->criarPaciente('Paciente Alvo Duplo');
        $origem = $this->criarPaciente('Paciente Origem Duplo');

        $mergeResponse = $this->postJson($this->baseUrl('/pacientes/mesclagens'), [
            'paciente_alvo_id' => $alvo->id,
            'pacientes_origem_ids' => [$origem->id],
        ]);

        $mergeResponse->assertCreated();
        $mesclagemId = $mergeResponse->json('data.id');

        // Primeira reversão — OK
        $this->postJson($this->baseUrl("/pacientes/mesclagens/{$mesclagemId}/reverter"))
            ->assertNoContent();

        // Segunda reversão — 422
        $response = $this->postJson($this->baseUrl("/pacientes/mesclagens/{$mesclagemId}/reverter"));
        $response->assertUnprocessable();
    }
}
