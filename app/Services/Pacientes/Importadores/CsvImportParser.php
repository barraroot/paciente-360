<?php

namespace App\Services\Pacientes\Importadores;

use League\Csv\Reader;

/**
 * T182 — Parser de CSV para importação de pacientes.
 *
 * Suporta:
 *  - BOM UTF-8 (detectado e removido automaticamente pelo League\Csv).
 *  - Separador `,` (padrão; configurável).
 *  - Normalização de cabeçalhos para snake_case (`Nome` → `nome`, `CPF` → `cpf`).
 */
final class CsvImportParser implements ImportParser
{
    private Reader $reader;

    /** @var list<string> */
    private array $normalizedHeaders;

    private int $totalLinhas;

    public function __construct(string $path, string $separator = ',')
    {
        $this->reader = Reader::createFromPath($path, 'r');
        $this->reader->setDelimiter($separator);
        $this->reader->setHeaderOffset(0);

        $rawHeaders = $this->reader->getHeader();
        $this->normalizedHeaders = array_map([$this, 'normalizeHeader'], $rawHeaders);

        // Contagem: total de registros (sem cabeçalho)
        $this->totalLinhas = count($this->reader);
    }

    public function iterate(): \Generator
    {
        $lineNumber = 1; // 1-based excluindo cabeçalho

        foreach ($this->reader->getRecords($this->normalizedHeaders) as $record) {
            /** @var array<string, string> $record */
            yield [
                'linha' => $lineNumber,
                'dados' => array_map('strval', $record),
            ];
            $lineNumber++;
        }
    }

    public function totalLinhas(): ?int
    {
        return $this->totalLinhas;
    }

    /**
     * @return list<string>
     */
    public function cabecalhos(): array
    {
        return $this->normalizedHeaders;
    }

    /**
     * Normaliza um cabeçalho para snake_case sem acentos.
     * "Nome" → "nome", "CPF" → "cpf", "Data Nascimento" → "data_nascimento".
     */
    private function normalizeHeader(string $header): string
    {
        // Remove BOM se presente
        $header = ltrim($header, "\xEF\xBB\xBF");

        // Lowercase
        $header = mb_strtolower(trim($header), 'UTF-8');

        // Remove acentos
        $header = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header) ?: $header;

        // Substitui espaços e hífens por underscore
        $header = preg_replace('/[\s\-]+/', '_', $header) ?? $header;

        // Remove caracteres não alfanuméricos exceto underscore
        $header = preg_replace('/[^a-z0-9_]/', '', $header) ?? $header;

        return $header;
    }
}
