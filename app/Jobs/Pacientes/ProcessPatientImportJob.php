<?php

namespace App\Jobs\Pacientes;

use App\Events\Paciente\PacientesImportados;
use App\Jobs\TenantAwareJob;
use App\Models\Importacao;
use App\Models\Paciente;
use App\Models\Scopes\TenantScope;
use App\Services\Pacientes\Importadores\CsvImportParser;
use App\Services\Pacientes\Importadores\ImportParser;
use App\Services\Pacientes\Importadores\LinhaImportacaoValidator;
use App\Services\Pacientes\Importadores\XlsxImportParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * T181 — Job de processamento assíncrono de importação de pacientes.
 *
 * Estratégia de idempotência:
 *  - Hash SHA-256 verificado no início de cada execução; mismatch → `failed`.
 *  - Checkpoint gravado a cada lote de 100 linhas (config `paciente.import.batch_size`).
 *  - Retomada: lê `checkpoint.linhas_processadas` e pula as linhas já processadas.
 *
 * @see App\Jobs\TenantAwareJob
 */
final class ProcessPatientImportJob extends TenantAwareJob
{
    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(public readonly int $importacaoId)
    {
        parent::__construct();
    }

    protected function run(): void
    {
        $importacao = Importacao::findOrFail($this->importacaoId);

        // Resolve caminho físico do arquivo
        $path = $this->resolveStoragePath($importacao->arquivo_path);

        // Verifica existência do arquivo
        if (! file_exists($path)) {
            $this->markFailed($importacao, 'arquivo_nao_encontrado: caminho de armazenamento não existe');

            return;
        }

        // Hash check — idempotência em retry
        $currentHash = hash_file('sha256', $path);
        if ($currentHash !== $importacao->arquivo_hash) {
            $this->markFailed($importacao, 'hash_mismatch_on_retry');

            return;
        }

        // Marca como processing na primeira execução real
        if (in_array($importacao->status, ['pending', 'retrying'], true)) {
            $importacao->update([
                'status' => 'processing',
                'started_at' => $importacao->started_at ?? now(),
            ]);
        }

        $extension = strtolower(pathinfo($importacao->arquivo_path, PATHINFO_EXTENSION));
        $parser = $this->makeParser($path, $extension);
        $validator = new LinhaImportacaoValidator;

        $checkpoint = $importacao->checkpoint ?? [];
        $linhasJaProcessadas = (int) ($checkpoint['linhas_processadas'] ?? 0);
        $contadores = $checkpoint['contadores'] ?? ['criados' => 0, 'atualizados' => 0, 'erros' => 0];

        $batchSize = (int) config('paciente.import.batch_size', 100);
        $batchBuffer = [];
        $relatorioGlobal = [];

        $lineIndex = 0;

        foreach ($parser->iterate() as $item) {
            $lineIndex++;

            // Pula linhas já processadas (retomada do checkpoint)
            if ($lineIndex <= $linhasJaProcessadas) {
                continue;
            }

            $batchBuffer[] = $item;

            if (count($batchBuffer) >= $batchSize) {
                $batchRelatorio = $this->processBatch($batchBuffer, $importacao, $validator, $contadores);
                $relatorioGlobal = array_merge($relatorioGlobal, $batchRelatorio);
                $batchBuffer = [];

                // Atualiza checkpoint
                $importacao->update([
                    'checkpoint' => [
                        'linhas_processadas' => $lineIndex,
                        'contadores' => $contadores,
                    ],
                    'relatorio' => $relatorioGlobal,
                ]);
            }
        }

        // Processa lote restante
        if (! empty($batchBuffer)) {
            $batchRelatorio = $this->processBatch($batchBuffer, $importacao, $validator, $contadores);
            $relatorioGlobal = array_merge($relatorioGlobal, $batchRelatorio);
        }

        // Determina status final
        $statusFinal = $contadores['erros'] > 0 ? 'partial_failure' : 'completed';

        $importacao->update([
            'status' => $statusFinal,
            'finished_at' => now(),
            'checkpoint' => [
                'linhas_processadas' => $lineIndex,
                'contadores' => $contadores,
            ],
            'relatorio' => $relatorioGlobal,
        ]);

        // Dispara evento de auditoria
        PacientesImportados::dispatch(
            $importacao->id,
            $contadores,
            $importacao->arquivo_hash,
        );
    }

    /**
     * @param array<int, array{linha: int, dados: array<string, string>}> $batch
     * @param array<string, int> $contadores passado por referência
     * @return array<int, array<string, mixed>>
     */
    private function processBatch(
        array $batch,
        Importacao $importacao,
        LinhaImportacaoValidator $validator,
        array &$contadores,
    ): array {
        $relatorio = [];

        DB::transaction(function () use ($batch, $importacao, $validator, &$contadores, &$relatorio): void {
            foreach ($batch as $item) {
                $numeroLinha = $item['linha'];
                $dados = $item['dados'];

                $resultado = $validator->validate($dados);

                if (! $resultado['ok']) {
                    $contadores['erros']++;
                    $relatorio[] = [
                        'linha' => $numeroLinha,
                        'resultado' => 'erro',
                        'motivo' => $resultado['motivo'],
                    ];

                    continue;
                }

                $dataNorm = $resultado['data_normalizada'];
                $statusInicial = $dataNorm['status_linha'] ?? $importacao->status_inicial_pacientes;
                unset($dataNorm['status_linha']);

                $resultadoDedup = $this->deduplicarOuCriar($dataNorm, $statusInicial, $importacao->tenant_id);

                if ($resultadoDedup === 'criado') {
                    $contadores['criados']++;
                } else {
                    $contadores['atualizados']++;
                }

                $relatorio[] = [
                    'linha' => $numeroLinha,
                    'resultado' => $resultadoDedup,
                    'motivo' => null,
                ];
            }
        });

        return $relatorio;
    }

    /**
     * Busca paciente existente por CPF (ou telefone como fallback) e atualiza
     * apenas campos vazios. Se não encontrar, cria novo paciente.
     *
     * @param array<string, mixed> $data
     * @return string 'criado' | 'atualizado'
     */
    private function deduplicarOuCriar(array $data, string $statusInicial, int $tenantId): string
    {
        $existente = $this->buscarExistente($data, $tenantId);

        if ($existente !== null) {
            $this->atualizarCamposVazios($existente, $data);

            return 'atualizado';
        }

        Paciente::create([
            'tenant_id' => $tenantId,
            'nome' => $data['nome'],
            'cpf' => $data['cpf'] ?? null,
            'telefone_primario' => $data['telefone_primario'] ?? null,
            'email' => $data['email'] ?? null,
            'data_nascimento' => $data['data_nascimento'] ?? null,
            'status' => $statusInicial,
            'origem' => $data['origem'] ?? 'presencial',
            'origem_detalhe' => $data['origem_detalhe'] ?? null,
            'origem_origem' => 'canal',
        ]);

        return 'criado';
    }

    private function buscarExistente(array $data, int $tenantId): ?Paciente
    {
        $cpf = $data['cpf'] ?? null;

        if (! empty($cpf)) {
            /** @var Paciente|null $paciente */
            $paciente = Paciente::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->where('cpf', $cpf)
                ->whereNull('anonimizado_em')
                ->first();

            if ($paciente !== null) {
                return $paciente;
            }
        }

        // Fallback: match por telefone quando CPF ausente
        $telefone = $data['telefone_primario'] ?? null;
        if (empty($cpf) && ! empty($telefone)) {
            /** @var Paciente|null $paciente */
            $paciente = Paciente::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->where('telefone_primario', $telefone)
                ->whereNull('anonimizado_em')
                ->first();

            return $paciente;
        }

        return null;
    }

    /**
     * Atualiza apenas campos vazios no paciente existente.
     * Preserva campos preenchidos (mais completo / pré-existente vence).
     *
     * @param array<string, mixed> $data
     */
    private function atualizarCamposVazios(Paciente $paciente, array $data): void
    {
        $camposAtualizaveis = ['email', 'telefone_primario', 'data_nascimento', 'origem_detalhe'];
        $updates = [];

        foreach ($camposAtualizaveis as $campo) {
            if (isset($data[$campo]) && $data[$campo] !== null && $data[$campo] !== '') {
                $valorAtual = $paciente->$campo;
                if ($valorAtual === null || $valorAtual === '') {
                    $updates[$campo] = $data[$campo];
                }
            }
        }

        if (! empty($updates)) {
            $paciente->update($updates);
        }
    }

    private function makeParser(string $path, string $extension): ImportParser
    {
        if ($extension === 'csv') {
            return new CsvImportParser($path);
        }

        return new XlsxImportParser($path);
    }

    /**
     * Resolve o caminho físico a partir do arquivo_path armazenado.
     *
     * arquivo_path = "imports/{tenant_id}/filename.csv" (relativo ao disk `local`)
     * ou "{tenant_id}/filename.csv" (relativo ao disk `imports`).
     *
     * Tenta primeiro o disk `imports` com o path sem o prefixo "imports/",
     * depois fallback para o disk `local` com o path completo.
     */
    private function resolveStoragePath(string $arquivoPath): string
    {
        // Tenta disk `imports`: remove prefixo "imports/" se presente
        $pathNoImports = preg_replace('#^imports/#', '', $arquivoPath) ?? $arquivoPath;

        // Verifica se existe no disk `imports`
        if (Storage::disk('imports')->exists($pathNoImports)) {
            return Storage::disk('imports')->path($pathNoImports);
        }

        // Fallback: tenta disk `local` com o path completo
        return Storage::disk('local')->path($arquivoPath);
    }

    private function markFailed(Importacao $importacao, string $motivo): void
    {
        $importacao->update([
            'status' => 'failed',
            'finished_at' => now(),
            'relatorio' => [['linha' => 0, 'resultado' => 'erro', 'motivo' => $motivo]],
        ]);
    }

    public function failed(Throwable $e): void
    {
        try {
            $importacao = Importacao::find($this->importacaoId);
            if ($importacao !== null && ! in_array($importacao->status, ['completed', 'partial_failure', 'failed'], true)) {
                // Sanitizar mensagem de erro: remover paths internos
                $motivo = preg_replace('/\/[\w\/\-\.]+/', '[path]', $e->getMessage()) ?? $e->getMessage();
                $motivo = mb_substr($motivo, 0, 500);

                $importacao->update([
                    'status' => 'failed',
                    'finished_at' => now(),
                    'relatorio' => [['linha' => 0, 'resultado' => 'erro', 'motivo' => $motivo]],
                ]);
            }
        } catch (Throwable) {
            // Evita cascata de exceções no handler
        }
    }
}
