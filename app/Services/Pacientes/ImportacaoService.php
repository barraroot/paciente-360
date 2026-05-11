<?php

namespace App\Services\Pacientes;

use App\Jobs\Pacientes\ProcessPatientImportJob;
use App\Models\Importacao;
use App\Models\User;
use App\Services\Pacientes\Importadores\CsvImportParser;
use App\Services\Pacientes\Importadores\ImportParser;
use App\Services\Pacientes\Importadores\XlsxImportParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * T180 — Serviço de criação e despacho de importações de pacientes (US-3.3).
 *
 * Responsabilidades:
 *  1. Validar tamanho, extensão, contagem de linhas e cabeçalho sincronamente.
 *  2. Persistir arquivo em `storage/app/imports/{tenant_id}/`.
 *  3. Calcular SHA-256 e criar registro em `importacoes`.
 *  4. Despachar `ProcessPatientImportJob` na fila `imports`.
 *
 * @see App\Jobs\Pacientes\ProcessPatientImportJob
 */
final class ImportacaoService
{
    /**
     * @throws InvalidArgumentException quando arquivo é inválido.
     */
    public function create(UploadedFile $file, string $statusInicial, User $executor): Importacao
    {
        $tenant = app('tenant');
        $maxBytes = config('paciente.import.max_size_mb', 5) * 1024 * 1024;
        $maxRows = (int) config('paciente.import.max_rows', 10000);

        // Validar tamanho
        if ($file->getSize() > $maxBytes) {
            throw new InvalidArgumentException('arquivo_muito_grande');
        }

        // Validar extensão
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'xlsx', 'xls'], true)) {
            throw new InvalidArgumentException('formato_nao_suportado');
        }

        $realPath = $file->getRealPath();

        // SHA-256 do arquivo original
        $hash = hash_file('sha256', $realPath);
        if ($hash === false) {
            throw new InvalidArgumentException('arquivo_ilegivel');
        }

        // Criar parser temporário para validar cabeçalho e contar linhas
        $parser = $this->makeParser($realPath, $extension);
        $cabecalhos = $parser->cabecalhos();

        if (! in_array('nome', $cabecalhos, true)) {
            throw new InvalidArgumentException('cabecalho_sem_coluna_nome');
        }

        $totalLinhas = $parser->totalLinhas() ?? 0;
        if ($totalLinhas > $maxRows) {
            throw new InvalidArgumentException('arquivo_muitas_linhas');
        }

        // Persistir arquivo
        $ulid = (string) Str::ulid();
        $storedName = "{$ulid}.{$extension}";
        $tenantDir = $tenant->id;

        $file->storeAs($tenantDir, $storedName, 'imports');

        $arquivoPath = "imports/{$tenantDir}/{$storedName}";

        // Criar registro
        $importacao = Importacao::create([
            'tenant_id' => $tenant->id,
            'executor_id' => $executor->id,
            'arquivo_path' => $arquivoPath,
            'arquivo_nome_original' => $file->getClientOriginalName(),
            'arquivo_hash' => $hash,
            'arquivo_tamanho_bytes' => $file->getSize(),
            'total_linhas' => $totalLinhas,
            'status' => 'pending',
            'status_inicial_pacientes' => $statusInicial,
            'checkpoint' => [],
        ]);

        // Despachar job
        ProcessPatientImportJob::dispatch($importacao->id)
            ->onQueue('imports')
            ->onConnection('imports');

        return $importacao;
    }

    private function makeParser(string $path, string $extension): ImportParser
    {
        if ($extension === 'csv') {
            return new CsvImportParser($path);
        }

        return new XlsxImportParser($path);
    }
}
