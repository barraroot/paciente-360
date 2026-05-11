<?php

namespace App\Services\Pacientes;

use App\Models\Paciente;
use App\Support\Cpf\CpfValidator;
use App\Support\Csv\CsvExporter;
use Illuminate\Database\Eloquent\Builder;

/**
 * T192 — Serviço de exportação de pacientes em CSV (US-3.3).
 *
 * Estratégia:
 *  - Streaming via `chunkById` para evitar carregar todos os registros.
 *  - Hash SHA-256 incremental via `hash_init` / `hash_update` / `hash_final`.
 *  - `CsvExporter::escapeFormulaInjection()` aplicado em cada célula.
 *  - NÃO persiste arquivo no servidor — apenas streaming.
 *
 * @see App\Support\Csv\CsvExporter
 */
final class ExportacaoService
{
    /**
     * Colunas exportadas (ordem do CSV).
     *
     * @var list<string>
     */
    public const CABECALHOS = [
        'Nome',
        'CPF',
        'Telefone',
        'E-mail',
        'Status',
        'Tags',
        'Profissional',
        'Convênio Principal',
        'Origem',
        'Data Cadastro',
    ];

    public function __construct(
        private readonly CsvExporter $csvExporter,
    ) {}

    /**
     * Escreve CSV de pacientes em php://output e retorna metadados.
     *
     * @param array<string, mixed> $filters Filtros aplicados (para auditoria).
     * @param callable $writer Callable que recebe a linha a escrever (handle do output).
     * @return array{hash: string, contagem: int, tamanho_bytes: int}
     */
    public function stream(array $filters, callable $writer): array
    {
        $hashCtx = hash_init('sha256');
        $contagem = 0;
        $tamanhoBytes = 0;

        $out = fopen('php://output', 'w');
        if ($out === false) {
            return ['hash' => '', 'contagem' => 0, 'tamanho_bytes' => 0];
        }

        // BOM UTF-8
        $bom = "\xEF\xBB\xBF";
        fwrite($out, $bom);
        hash_update($hashCtx, $bom);
        $tamanhoBytes += strlen($bom);

        // Cabeçalho
        $headerLine = $this->toCsvLine(self::CABECALHOS);
        fwrite($out, $headerLine);
        hash_update($hashCtx, $headerLine);
        $tamanhoBytes += strlen($headerLine);

        // Dados via chunkById
        $query = $this->buildQuery($filters);

        $query->with(['tags', 'convenios.convenio', 'profissionalResponsavel'])
            ->chunkById(500, function ($pacientes) use ($out, $hashCtx, &$contagem, &$tamanhoBytes): void {
                foreach ($pacientes as $paciente) {
                    $row = $this->buildRow($paciente);
                    $line = $this->toCsvLine($row);
                    fwrite($out, $line);
                    hash_update($hashCtx, $line);
                    $tamanhoBytes += strlen($line);
                    $contagem++;
                }
            });

        fclose($out);

        return [
            'hash' => hash_final($hashCtx),
            'contagem' => $contagem,
            'tamanho_bytes' => $tamanhoBytes,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return Builder<Paciente>
     */
    private function buildQuery(array $filters): Builder
    {
        $query = Paciente::query()->whereNull('anonimizado_em');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['origem'])) {
            $query->where('origem', $filters['origem']);
        }

        if (! empty($filters['profissional_responsavel_id'])) {
            $query->where('profissional_responsavel_id', $filters['profissional_responsavel_id']);
        }

        if (! empty($filters['q'])) {
            $term = '%'.addcslashes($filters['q'], '%_').'%';
            $query->where(function ($q) use ($term): void {
                $q->where('nome', 'ilike', $term)
                    ->orWhere('telefone_primario', 'ilike', $term)
                    ->orWhere('email', 'ilike', $term);
            });
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    private function buildRow(Paciente $paciente): array
    {
        $cpf = $paciente->cpf ? CpfValidator::format($paciente->cpf) : '';

        $tags = $paciente->tags
            ->map(fn ($t) => $t->nome)
            ->implode(', ');

        $profissional = $paciente->profissionalResponsavel?->name ?? '';

        $convenio = $paciente->convenios
            ->firstWhere('papel', 'principal')
            ?->convenio
            ?->nome ?? '';

        $cells = [
            $paciente->nome,
            $cpf,
            $paciente->telefone_primario ?? '',
            $paciente->email ?? '',
            $paciente->status,
            $tags,
            $profissional,
            $convenio,
            $paciente->origem,
            $paciente->created_at->format('d/m/Y'),
        ];

        return array_map([$this->csvExporter, 'escapeFormulaInjection'], $cells);
    }

    /**
     * @param list<string> $cells
     */
    private function toCsvLine(array $cells): string
    {
        $handle = fopen('php://memory', 'w');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, $cells, ',', '"', '');
        rewind($handle);
        $line = (string) stream_get_contents($handle);
        fclose($handle);

        return $line;
    }
}
