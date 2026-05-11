<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Jobs\Pacientes\ProcessPatientImportJob;
use App\Models\Importacao;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T174 — Testes de retomada via checkpoint (R5).
 */
class ImportacaoRetomadaTest extends TestCase
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

    /** @test */
    public function test_retomada_continua_do_ultimo_checkpoint(): void
    {
        // Arquivo com 3 pacientes reais
        $csv = "nome,cpf,email\nPac1,,p1@ex.com\nPac2,,p2@ex.com\nPac3,,p3@ex.com\n";
        $hash = hash('sha256', $csv);

        Storage::disk('imports')->put("{$this->tenant->id}/resume.csv", $csv);

        // Simula que 2 já foram processados (checkpoint.linhas_processadas = 2)
        // e já existem 2 pacientes no banco
        Paciente::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Pac1',
            'status' => 'lead',
            'origem' => 'presencial',
            'origem_origem' => 'canal',
        ]);

        Paciente::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Pac2',
            'status' => 'lead',
            'origem' => 'presencial',
            'origem_origem' => 'canal',
        ]);

        $importacao = Importacao::create([
            'tenant_id' => $this->tenant->id,
            'executor_id' => $this->user->id,
            'arquivo_path' => "imports/{$this->tenant->id}/resume.csv",
            'arquivo_nome_original' => 'test.csv',
            'arquivo_hash' => $hash,
            'arquivo_tamanho_bytes' => strlen($csv),
            'total_linhas' => 3,
            'status' => 'retrying',
            'status_inicial_pacientes' => 'lead',
            'checkpoint' => [
                'linhas_processadas' => 2,
                'contadores' => ['criados' => 2, 'atualizados' => 0, 'erros' => 0],
            ],
        ]);

        $this->app->instance('tenant', $this->tenant);
        $job = new ProcessPatientImportJob($importacao->id);
        $job->handle();

        $importacao->refresh();
        $this->assertEquals('completed', $importacao->status);

        // Apenas Pac3 deve ter sido criado (Pac1 e Pac2 já existiam por nome — sem CPF, sem match)
        // O job processa da linha 3 em diante, então apenas Pac3 é novo
        $this->assertDatabaseHas('pacientes', [
            'tenant_id' => $this->tenant->id,
            'nome' => 'Pac3',
        ]);
    }

    /** @test */
    public function test_hash_mismatch_em_retry_marca_failed(): void
    {
        $csv = "nome,cpf,email\nJoao,,joao@ex.com\n";
        $hashErrado = str_repeat('a', 64); // hash falso

        Storage::disk('imports')->put("{$this->tenant->id}/mismatch.csv", $csv);

        $importacao = Importacao::create([
            'tenant_id' => $this->tenant->id,
            'executor_id' => $this->user->id,
            'arquivo_path' => "imports/{$this->tenant->id}/mismatch.csv",
            'arquivo_nome_original' => 'test.csv',
            'arquivo_hash' => $hashErrado,
            'arquivo_tamanho_bytes' => strlen($csv),
            'total_linhas' => 1,
            'status' => 'retrying',
            'status_inicial_pacientes' => 'lead',
            'checkpoint' => [],
        ]);

        $this->app->instance('tenant', $this->tenant);
        $job = new ProcessPatientImportJob($importacao->id);
        $job->handle();

        $importacao->refresh();
        $this->assertEquals('failed', $importacao->status);

        $relatorio = $importacao->relatorio ?? [];
        $motivo = $relatorio[0]['motivo'] ?? '';
        $this->assertStringContainsString('hash_mismatch', $motivo);
    }

    /** @test */
    public function test_arquivo_inexistente_marca_failed(): void
    {
        $importacao = Importacao::create([
            'tenant_id' => $this->tenant->id,
            'executor_id' => $this->user->id,
            'arquivo_path' => "imports/{$this->tenant->id}/nao_existe.csv",
            'arquivo_nome_original' => 'nao_existe.csv',
            'arquivo_hash' => str_repeat('b', 64),
            'arquivo_tamanho_bytes' => 100,
            'total_linhas' => 1,
            'status' => 'pending',
            'status_inicial_pacientes' => 'lead',
            'checkpoint' => [],
        ]);

        $this->app->instance('tenant', $this->tenant);
        $job = new ProcessPatientImportJob($importacao->id);
        $job->handle();

        $importacao->refresh();
        $this->assertEquals('failed', $importacao->status);

        $relatorio = $importacao->relatorio ?? [];
        $motivo = $relatorio[0]['motivo'] ?? '';
        $this->assertNotEmpty($motivo);
    }
}
