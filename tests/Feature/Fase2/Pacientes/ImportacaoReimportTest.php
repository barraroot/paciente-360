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
 * T172 — Testes de reimportação (AC-3.3.7).
 *
 * Reimport atualiza apenas campos vazios; preserva campos preenchidos;
 * match por telefone quando CPF ausente.
 */
class ImportacaoReimportTest extends TestCase
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
    public function test_reimport_atualiza_apenas_campos_vazios(): void
    {
        $cpfCanon = '52998224725';

        $paciente = Paciente::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Ana Lima',
            'cpf' => $cpfCanon,
            'email' => null,
            'status' => 'lead',
            'origem' => 'presencial',
            'origem_origem' => 'manual',
        ]);

        $csv = $this->buildCsv([
            ['nome' => 'Ana Lima', 'cpf' => '529.982.247-25', 'email' => 'ana@nova.com'],
        ]);
        $this->runImport($csv, 'lead');

        $paciente->refresh();
        $this->assertEquals('ana@nova.com', $paciente->email);
    }

    /** @test */
    public function test_reimport_preserva_campos_preenchidos(): void
    {
        $cpfCanon = '52998224725';

        $paciente = Paciente::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'João',
            'cpf' => $cpfCanon,
            'email' => 'a@b.com',
            'status' => 'lead',
            'origem' => 'presencial',
            'origem_origem' => 'manual',
        ]);

        // Reimport tenta sobrescrever nome e email
        $csv = $this->buildCsv([
            ['nome' => 'JOAO', 'cpf' => '529.982.247-25', 'email' => 'b@c.com'],
        ]);
        $this->runImport($csv, 'lead');

        $paciente->refresh();
        // Campos preenchidos devem ser preservados (mais completo / pré-existente)
        $this->assertEquals('João', $paciente->nome);
        $this->assertEquals('a@b.com', $paciente->email);
    }

    /** @test */
    public function test_reimport_match_por_telefone_quando_cpf_ausente(): void
    {
        $telefone = '+5511999999999';

        $paciente = Paciente::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Maria Souza',
            'cpf' => null,
            'telefone_primario' => $telefone,
            'email' => null,
            'status' => 'lead',
            'origem' => 'presencial',
            'origem_origem' => 'manual',
        ]);

        // Mesmo telefone sem CPF, com email
        $csv = $this->buildCsv([
            ['nome' => 'Maria Souza', 'cpf' => '', 'telefone_primario' => '(11) 99999-9999', 'email' => 'maria@ex.com'],
        ]);
        $this->runImport($csv, 'lead');

        $paciente->refresh();
        $this->assertEquals('maria@ex.com', $paciente->email);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    /**
     * @param array<int, array<string, string>> $rows
     */
    private function buildCsv(array $rows): string
    {
        $headers = ['nome', 'cpf', 'data_nascimento', 'telefone_primario', 'email', 'status', 'origem', 'origem_detalhe'];
        $lines = [implode(',', $headers)];

        foreach ($rows as $row) {
            $cells = array_map(fn (string $h) => $row[$h] ?? '', $headers);
            $lines[] = implode(',', $cells);
        }

        return implode("\n", $lines)."\n";
    }

    private function runImport(string $csv, string $statusInicial): Importacao
    {
        $hash = hash('sha256', $csv);
        $filename = uniqid('import_').'.csv';

        Storage::disk('imports')->put("{$this->tenant->id}/{$filename}", $csv);

        $importacao = Importacao::create([
            'tenant_id' => $this->tenant->id,
            'executor_id' => $this->user->id,
            'arquivo_path' => "imports/{$this->tenant->id}/{$filename}",
            'arquivo_nome_original' => 'pacientes.csv',
            'arquivo_hash' => $hash,
            'arquivo_tamanho_bytes' => strlen($csv),
            'total_linhas' => substr_count($csv, "\n"),
            'status' => 'pending',
            'status_inicial_pacientes' => $statusInicial,
            'checkpoint' => [],
        ]);

        $this->app->instance('tenant', $this->tenant);
        $job = new ProcessPatientImportJob($importacao->id);
        $job->handle();

        return $importacao->refresh();
    }
}
