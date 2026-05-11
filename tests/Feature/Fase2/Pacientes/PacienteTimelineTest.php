<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Models\EventoTimeline;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T140 — Testes de timeline unificada do paciente (US-3.2).
 *
 * Cobre AC-3.2.1, AC-3.2.2, AC-3.2.3, AC-3.2.6, AC-3.2.8.
 */
class PacienteTimelineTest extends TestCase
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

    /**
     * Cria um paciente via API (dispara eventos e timeline) e retorna o paciente.
     *
     * @param array<string, mixed> $data
     */
    private function criarPacienteViaApi(array $data = []): Paciente
    {
        $payload = array_merge([
            'nome' => 'Paciente Teste',
            'telefone_primario' => '(31) 9'.rand(1000, 9999).'-'.rand(1000, 9999),
            'status' => 'lead',
        ], $data);

        $response = $this->postJson($this->baseUrl('/pacientes'), $payload);
        $response->assertCreated();

        return Paciente::find($response->json('data.id'));
    }

    /** @test */
    public function test_timeline_lista_eventos_em_ordem_cronologica_reversa(): void
    {
        // Cria paciente via API — dispara PacienteCriado → evento na timeline
        $paciente = $this->criarPacienteViaApi(['nome' => 'Maria Souza']);

        // Garante que há pelo menos 1 evento de criação na timeline
        $this->assertDatabaseHas('eventos_timeline', [
            'paciente_id' => $paciente->id,
            'tipo' => 'paciente.criado',
        ]);

        // Altera email (campo significativo em tracked_fields)
        $this->patchJson(
            $this->baseUrl("/pacientes/{$paciente->id}"),
            ['email' => 'atualizado@email.com'],
        )->assertSuccessful();

        // GET timeline
        $response = $this->getJson($this->baseUrl("/pacientes/{$paciente->id}/timeline"));
        $response->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(2, count($data));

        // Verifica ordem cronológica reversa (mais recente primeiro)
        for ($i = 0; $i < count($data) - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                $data[$i + 1]['created_at'],
                $data[$i]['created_at'],
                'Eventos devem estar em ordem cronológica reversa.',
            );
        }

        // Deve conter evento de criação e atualização
        $tipos = array_column($data, 'tipo');
        $this->assertContains('paciente.criado', $tipos);
        $this->assertContains('paciente.atualizado', $tipos);
    }

    /** @test */
    public function test_timeline_so_registra_campos_significativos(): void
    {
        $paciente = $this->criarPacienteViaApi();

        $eventoAntes = EventoTimeline::where('paciente_id', $paciente->id)->count();

        // Altera endereço — NÃO está em tracked_fields (per config/paciente.php)
        $this->patchJson(
            $this->baseUrl("/pacientes/{$paciente->id}"),
            ['endereco' => ['logradouro' => 'Rua Teste', 'cep' => '30000000']],
        )->assertSuccessful();

        $eventoDepois1 = EventoTimeline::where('paciente_id', $paciente->id)->count();

        // Endereço não rastreado: contagem deve ser a mesma
        $this->assertEquals($eventoAntes, $eventoDepois1);

        // Agora altera email — ESTÁ em tracked_fields
        $this->patchJson(
            $this->baseUrl("/pacientes/{$paciente->id}"),
            ['email' => 'novo@email.com'],
        )->assertSuccessful();

        $eventoDepois2 = EventoTimeline::where('paciente_id', $paciente->id)->count();

        // Email rastreado: contagem deve aumentar
        $this->assertGreaterThan($eventoDepois1, $eventoDepois2);
    }

    /** @test */
    public function test_timeline_filtra_por_tipo(): void
    {
        $paciente = $this->criarPacienteViaApi(['status' => 'lead']);

        // Altera status para gerar evento paciente.status_alterado
        $this->patchJson(
            $this->baseUrl("/pacientes/{$paciente->id}/status"),
            ['status' => 'ativo'],
        )->assertSuccessful();

        $response = $this->getJson(
            $this->baseUrl("/pacientes/{$paciente->id}/timeline?tipo=paciente.criado"),
        );
        $response->assertOk();

        $data = $response->json('data');
        $tipos = array_column($data, 'tipo');

        // Apenas eventos do tipo filtrado
        foreach ($tipos as $tipo) {
            $this->assertEquals('paciente.criado', $tipo);
        }
    }

    /** @test */
    public function test_timeline_paginacao_cursor(): void
    {
        // Cria um paciente com tenant correto via factory (não precisa de eventos)
        $paciente = Paciente::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Cria 60 eventos manualmente via INSERT bulk para performance
        $now = now();
        $rows = [];
        for ($i = 0; $i < 60; $i++) {
            $rows[] = [
                'tenant_id' => $this->tenant->id,
                'paciente_id' => $paciente->id,
                'tipo' => 'paciente.atualizado',
                'autor_id' => $this->user->id,
                'actor_type' => 'user',
                'payload' => json_encode(['bulk' => $i]),
                'referencia_tipo' => null,
                'referencia_id' => null,
                'created_at' => $now->copy()->subSeconds($i)->toDateTimeString(),
            ];
        }
        DB::table('eventos_timeline')->insert($rows);

        // Primeira página com limit=25
        $response = $this->getJson($this->baseUrl("/pacientes/{$paciente->id}/timeline?limit=25"));
        $response->assertOk();

        $data1 = $response->json('data');
        $meta1 = $response->json('meta');

        $this->assertCount(25, $data1);
        $this->assertTrue($meta1['has_more']);
        $this->assertNotNull($meta1['next_cursor']);

        // Segunda página via cursor
        $cursor = $meta1['next_cursor'];
        $response2 = $this->getJson(
            $this->baseUrl("/pacientes/{$paciente->id}/timeline?limit=25&cursor={$cursor}"),
        );
        $response2->assertOk();

        $data2 = $response2->json('data');
        $this->assertNotEmpty($data2);

        // Verifica que não há sobreposição de IDs entre as duas páginas
        $ids1 = array_column($data1, 'id');
        $ids2 = array_column($data2, 'id');
        $this->assertEmpty(array_intersect($ids1, $ids2));
    }

    /** @test */
    public function test_timeline_404_para_paciente_de_outro_tenant(): void
    {
        $outraTenant = $this->bootstrapTenantWithRoles('outra-clinica');
        $pacienteOutro = Paciente::factory()->create([
            'tenant_id' => $outraTenant->id,
        ]);

        $this->getJson($this->baseUrl("/pacientes/{$pacienteOutro->id}/timeline"))
            ->assertNotFound();
    }

    /** @test */
    public function test_timeline_performance_1000_eventos(): void
    {
        $paciente = Paciente::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // INSERT bulk de 1000 eventos (evita factory para performance)
        $now = now();
        $rows = [];
        for ($i = 0; $i < 1000; $i++) {
            $rows[] = [
                'tenant_id' => $this->tenant->id,
                'paciente_id' => $paciente->id,
                'tipo' => 'paciente.atualizado',
                'autor_id' => null,
                'actor_type' => 'system',
                'payload' => json_encode(['idx' => $i]),
                'referencia_tipo' => null,
                'referencia_id' => null,
                'created_at' => $now->copy()->subSeconds($i)->toDateTimeString(),
            ];
        }
        // Insere em lotes de 500 para evitar parâmetros demais
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('eventos_timeline')->insert($chunk);
        }

        $start = microtime(true);

        $response = $this->getJson($this->baseUrl("/pacientes/{$paciente->id}/timeline?limit=50"));
        $response->assertOk();

        $elapsed = (microtime(true) - $start) * 1000; // ms

        $this->assertCount(50, $response->json('data'));
        $this->assertLessThan(
            500,
            $elapsed,
            "Timeline com 1000 eventos deve responder em < 500ms; levou {$elapsed}ms.",
        );
    }

    /** @test */
    public function test_timeline_inclui_actor(): void
    {
        $paciente = Paciente::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Insere evento com autor explícito
        DB::table('eventos_timeline')->insert([
            'tenant_id' => $this->tenant->id,
            'paciente_id' => $paciente->id,
            'tipo' => 'paciente.atualizado',
            'autor_id' => $this->user->id,
            'actor_type' => 'user',
            'payload' => json_encode(['test' => true]),
            'referencia_tipo' => null,
            'referencia_id' => null,
            'created_at' => now()->toDateTimeString(),
        ]);

        $response = $this->getJson($this->baseUrl("/pacientes/{$paciente->id}/timeline?tipo=paciente.atualizado"));
        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $evento = $data[0];
        $this->assertArrayHasKey('actor', $evento);
        $this->assertEquals('user', $evento['actor']['type']);
        $this->assertEquals($this->user->id, $evento['actor']['user_id']);
    }
}
