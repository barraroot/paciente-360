<?php

namespace App\Services\Pacientes\Importadores;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * T182 — Parser de XLSX para importação de pacientes.
 *
 * Carrega apenas a primeira planilha com `setReadDataOnly(true)`.
 * Primeira linha é o cabeçalho; normaliza para snake_case.
 */
final class XlsxImportParser implements ImportParser
{
    private Worksheet $sheet;

    /** @var list<string> */
    private array $normalizedHeaders;

    private int $totalLinhas;

    public function __construct(string $path)
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($path);
        $this->sheet = $spreadsheet->getActiveSheet();

        $this->normalizedHeaders = $this->extractHeaders();
        $this->totalLinhas = max(0, $this->sheet->getHighestDataRow() - 1); // -1 cabeçalho
    }

    public function iterate(): \Generator
    {
        $highestRow = $this->sheet->getHighestDataRow();

        for ($row = 2; $row <= $highestRow; $row++) {
            $dados = [];

            foreach ($this->normalizedHeaders as $col => $header) {
                $colLetter = Coordinate::stringFromColumnIndex($col + 1);
                $cell = $this->sheet->getCell("{$colLetter}{$row}");
                $dados[$header] = (string) $cell->getValue();
            }

            yield [
                'linha' => $row - 1, // 1-based excluindo cabeçalho
                'dados' => $dados,
            ];
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
     * @return list<string>
     */
    private function extractHeaders(): array
    {
        $highestCol = $this->sheet->getHighestDataColumn();
        $highestColIndex = Coordinate::columnIndexFromString($highestCol);

        $headers = [];
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $raw = (string) $this->sheet->getCell("{$colLetter}1")->getValue();
            $headers[] = $this->normalizeHeader($raw);
        }

        return $headers;
    }

    private function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header), 'UTF-8');
        $header = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header) ?: $header;
        $header = preg_replace('/[\s\-]+/', '_', $header) ?? $header;
        $header = preg_replace('/[^a-z0-9_]/', '', $header) ?? $header;

        return $header;
    }
}
