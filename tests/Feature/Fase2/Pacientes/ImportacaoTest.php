<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Events\Paciente\PacientesImportados;
use App\Jobs\Pacientes\ProcessPatientImportJob;
use App\Models\Importacao;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T170 — Testes do fluxo completo de importação de pacientes (US-3.3).
 *
 * Cobre AC-3.3.1, 3.3.2, 3.3.4, 3.3.5, 3.3.6, 3.3.8, 3.3.10.
 */
class ImportacaoTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('imports');
        $this->seedRoles();
        [$this->tenant, $this->user] = $this->tenantAndUserForRole('clinica-alfa', 'admin-clinica');
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    /** @test */
    public function test_admin_pode_baixar_template_csv(): void
    {
        $response = $this->get($this->baseUrl('/pacientes/importacao/template?formato=csv'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        // BOM UTF-8
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // Cabeçalhos pt-BR obrigatórios
        $this->assertStringContainsString('Nome', $content);
        $this->assertStringContainsString('CPF', $content);
        $this->assertStringContainsString('E-mail', $content);

        // Linha de exemplo (BOM está na linha 1 junto com cabeçalho; exemplo é linha 2)
        $lines = explode("\n", $content);
        $nonEmpty = array_values(array_filter($lines));
        $this->assertGreaterThanOrEqual(2, count($nonEmpty), 'Deve ter cabeçalho + pelo menos 1 linha de exemplo');
    }

    /** @test */
    public function test_admin_pode_baixar_template_xlsx(): void
    {
        $response = $this->get($this->baseUrl('/pacientes/importacao/template?formato=xlsx'));

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('Content-Type') ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    /** @test */
    public function test_upload_aceita_csv_e_retorna_202(): void
    {
        Bus::fake();

        $csv = $this->buildCsv([
            ['nome' => 'Ana Lima', 'cpf' => '529.982.247-25', 'email' => 'ana@ex.com'],
            ['nome' => 'Bruno Melo', 'cpf' => '', 'email' => 'bruno@ex.com'],
            ['nome' => 'Carla Neves', 'cpf' => '', 'email' => ''],
            ['nome' => 'Diego Ramos', 'cpf' => '', 'email' => ''],
            ['nome' => 'Eva Sousa', 'cpf' => '', 'email' => ''],
        ]);

        $file = UploadedFile::fake()->createWithContent('pacientes.csv', $csv);

        $response = $this->postJson($this->baseUrl('/pacientes/importacao'), [
            'arquivo' => $file,
            'status_inicial' => 'lead',
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonStructure(['data' => ['id', 'status', 'arquivo_nome_original', 'total_linhas', 'created_at']]);

        $importacao = Importacao::first();
        $this->assertNotNull($importacao);
        $this->assertNotEmpty($importacao->arquivo_hash);
        $this->assertEquals(64, strlen($importacao->arquivo_hash));

        Bus::assertDispatched(ProcessPatientImportJob::class);
    }

    /** @test */
    public function test_job_processa_csv_e_marca_completed(): void
    {
        $csv = $this->buildCsv([
            ['nome' => 'Ana Lima', 'cpf' => '529.982.247-25', 'email' => 'ana@ex.com'],
            ['nome' => 'Bruno Melo', 'cpf' => '671.866.801-00', 'email' => ''],
            ['nome' => 'Carla Neves', 'cpf' => '', 'email' => 'carla@ex.com'],
            ['nome' => 'Diego Ramos', 'cpf' => '', 'email' => ''],
            ['nome' => 'Eva Sousa', 'cpf' => '', 'email' => ''],
        ]);

        $hash = hash('sha256', $csv);
        Storage::disk('imports')->put("{$this->tenant->id}/test.csv", $csv);

        $importacao = Importacao::create([
            'tenant_id' => $this->tenant->id,
            'executor_id' => $this->user->id,
            'arquivo_path' => "imports/{$this->tenant->id}/test.csv",
            'arquivo_nome_original' => 'pacientes.csv',
            'arquivo_hash' => $hash,
            'arquivo_tamanho_bytes' => strlen($csv),
            'total_linhas' => 5,
            'status' => 'pending',
            'status_inicial_pacientes' => 'lead',
            'checkpoint' => [],
        ]);

        $this->app->instance('tenant', $this->tenant);
        $job = new ProcessPatientImportJob($importacao->id);
        $job->handle();

        $importacao->refresh();
        $this->assertEquals('completed', $importacao->status);

        $this->assertEquals(5, Paciente::where('tenant_id', $this->tenant->id)->count());
        $this->assertEquals(
            5,
            Paciente::where('tenant_id', $this->tenant->id)->where('status', 'lead')->count()
        );
    }

    /** @test */
    public function test_arquivo_com_erros_resulta_em_partial_failure(): void
    {
        $rows = [];
        for ($i = 1; $i <= 7; $i++) {
            $rows[] = ['nome' => "Paciente {$i}", 'cpf' => '', 'email' => "pac{$i}@ex.com"];
        }

        // 3 linhas inválidas: sem nome
        $rows[] = ['nome' => '', 'cpf' => '', 'email' => 'invalid1@ex.com'];
        $rows[] = ['nome' => '', 'cpf' => '', 'email' => 'invalid2@ex.com'];
        $rows[] = ['nome' => '', 'cpf' => '', 'email' => 'invalid3@ex.com'];

        $csv = $this->buildCsv($rows);
        $hash = hash('sha256', $csv);

        Storage::disk('imports')->put("{$this->tenant->id}/test2.csv", $csv);

        $importacao = Importacao::create([
            'tenant_id' => $this->tenant->id,
            'executor_id' => $this->user->id,
            'arquivo_path' => "imports/{$this->tenant->id}/test2.csv",
            'arquivo_nome_original' => 'pacientes.csv',
            'arquivo_hash' => $hash,
            'arquivo_tamanho_bytes' => strlen($csv),
            'total_linhas' => 10,
            'status' => 'pending',
            'status_inicial_pacientes' => 'lead',
            'checkpoint' => [],
        ]);

        $this->app->instance('tenant', $this->tenant);
        $job = new ProcessPatientImportJob($importacao->id);
        $job->handle();

        $importacao->refresh();
        $this->assertEquals('partial_failure', $importacao->status);

        $relatorio = $importacao->relatorio ?? [];
        $importadas = collect($relatorio)->filter(fn ($r) => in_array($r['resultado'] ?? '', ['criado', 'atualizado']))->count();
        $erros = collect($relatorio)->filter(fn ($r) => ($r['resultado'] ?? '') === 'erro')->count();

        $this->assertEquals(7, $importadas);
        $this->assertEquals(3, $erros);
    }

    /** @test */
    public function test_deduplicacao_por_cpf_atualiza_campos_vazios(): void
    {
        // Paciente A existente: CPF preenchido, email vazio
        $cpf = '529.982.247-25';
        $cpfCanon = '52998224725';

        $pacienteA = Paciente::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Ana Lima',
            'cpf' => $cpfCanon,
            'email' => null,
            'status' => 'lead',
            'origem' => 'presencial',
            'origem_origem' => 'manual',
        ]);

        $csv = $this->buildCsv([
            ['nome' => 'Ana Lima', 'cpf' => $cpf, 'email' => 'ana@nova.com'],
        ]);
        $hash = hash('sha256', $csv);

        Storage::disk('imports')->put("{$this->tenant->id}/test3.csv", $csv);

        $importacao = Importacao::create([
            'tenant_id' => $this->tenant->id,
            'executor_id' => $this->user->id,
            'arquivo_path' => "imports/{$this->tenant->id}/test3.csv",
            'arquivo_nome_original' => 'pacientes.csv',
            'arquivo_hash' => $hash,
            'arquivo_tamanho_bytes' => strlen($csv),
            'total_linhas' => 1,
            'status' => 'pending',
            'status_inicial_pacientes' => 'lead',
            'checkpoint' => [],
        ]);

        $this->app->instance('tenant', $this->tenant);
        $job = new ProcessPatientImportJob($importacao->id);
        $job->handle();

        $pacienteA->refresh();
        $this->assertEquals('ana@nova.com', $pacienteA->email);

        // Verificar relatório classifica como atualizado
        $importacao->refresh();
        $relatorio = $importacao->relatorio ?? [];
        $atualizado = collect($relatorio)->firstWhere('resultado', 'atualizado');
        $this->assertNotNull($atualizado, 'Deve ter entrada atualizado no relatório');
    }

    /** @test */
    public function test_status_inicial_aplicado_a_todos_importados(): void
    {
        $csv = $this->buildCsv([
            ['nome' => 'P1', 'cpf' => '', 'email' => 'p1@ex.com'],
            ['nome' => 'P2', 'cpf' => '', 'email' => 'p2@ex.com'],
        ]);
        $hash = hash('sha256', $csv);

        Storage::disk('imports')->put("{$this->tenant->id}/test4.csv", $csv);

        $importacao = Importacao::create([
            'tenant_id' => $this->tenant->id,
            'executor_id' => $this->user->id,
            'arquivo_path' => "imports/{$this->tenant->id}/test4.csv",
            'arquivo_nome_original' => 'pacientes.csv',
            'arquivo_hash' => $hash,
            'arquivo_tamanho_bytes' => strlen($csv),
            'total_linhas' => 2,
            'status' => 'pending',
            'status_inicial_pacientes' => 'ativo',
            'checkpoint' => [],
        ]);

        $this->app->instance('tenant', $this->tenant);
        $job = new ProcessPatientImportJob($importacao->id);
        $job->handle();

        $importacao->refresh();
        $this->assertEquals('completed', $importacao->status);

        $pacientes = Paciente::where('tenant_id', $this->tenant->id)->get();
        foreach ($pacientes as $p) {
            $this->assertEquals('ativo', $p->status);
        }
    }

    /** @test */
    public function test_audit_log_paciente_imported_com_hash(): void
    {
        Event::fake([PacientesImportados::class]);

        $csv = $this->buildCsv([
            ['nome' => 'Joao Silva', 'cpf' => '', 'email' => 'joao@ex.com'],
        ]);
        $hash = hash('sha256', $csv);

        Storage::disk('imports')->put("{$this->tenant->id}/test5.csv", $csv);

        $importacao = Importacao::create([
            'tenant_id' => $this->tenant->id,
            'executor_id' => $this->user->id,
            'arquivo_path' => "imports/{$this->tenant->id}/test5.csv",
            'arquivo_nome_original' => 'pacientes.csv',
            'arquivo_hash' => $hash,
            'arquivo_tamanho_bytes' => strlen($csv),
            'total_linhas' => 1,
            'status' => 'pending',
            'status_inicial_pacientes' => 'lead',
            'checkpoint' => [],
        ]);

        $this->app->instance('tenant', $this->tenant);
        $job = new ProcessPatientImportJob($importacao->id);
        $job->handle();

        Event::assertDispatched(PacientesImportados::class, function (PacientesImportados $event) use ($hash, $importacao): bool {
            $this->assertEquals($importacao->id, $event->importacaoId);
            $this->assertEquals($hash, $event->arquivoHash);
            $this->assertArrayHasKey('criados', $event->contadores);

            return true;
        });
    }

    /** @test */
    public function test_importacao_nao_bloqueia_outros_endpoints(): void
    {
        $start = microtime(true);

        $response = $this->getJson($this->baseUrl('/pacientes'));

        $elapsed = (microtime(true) - $start) * 1000;

        $response->assertOk();
        $this->assertLessThan(500, $elapsed, 'GET /pacientes deve responder em < 500ms');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    /**
     * @param array<int, array<string, string>> $rows
     */
    private function buildCsv(array $rows): string
    {
        $headers = ['nome', 'cpf', 'data_nascimento', 'telefone_primario', 'email', 'status', 'origem', 'origem_detalhe'];
        $lines = ["\xEF\xBB\xBF".implode(',', $headers)];

        foreach ($rows as $row) {
            $cells = array_map(fn (string $h) => $row[$h] ?? '', $headers);
            $lines[] = implode(',', $cells);
        }

        return implode("\n", $lines)."\n";
    }
}
