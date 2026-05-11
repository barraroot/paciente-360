<?php

namespace App\Services\Pacientes;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * T184 — Gerador de templates para importação de pacientes (US-3.3).
 *
 * Suporta CSV (streaming com BOM UTF-8) e XLSX (via PhpSpreadsheet).
 */
final class TemplateGenerator
{
    /**
     * Cabeçalhos pt-BR na ordem correta para o template.
     *
     * @var list<string>
     */
    private const CABECALHOS = [
        'Nome',
        'CPF',
        'Data Nascimento (DD/MM/AAAA)',
        'Telefone',
        'E-mail',
        'Status',
        'Origem',
        'Origem Detalhe',
    ];

    /**
     * Linha de exemplo para orientar o usuário.
     *
     * @var list<string>
     */
    private const LINHA_EXEMPLO = [
        'Maria da Silva',
        '123.456.789-09',
        '15/03/1985',
        '(11) 99999-9999',
        'maria@exemplo.com',
        'lead',
        'indicacao',
        'Indicação de amigo',
    ];

    public function streamCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            // BOM UTF-8
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, self::CABECALHOS, ',', '"', '');
            fputcsv($out, self::LINHA_EXEMPLO, ',', '"', '');

            fclose($out);
        }, 'template_importacao_pacientes.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template_importacao_pacientes.csv"',
        ]);
    }

    public function streamXlsx(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template');

        // Cabeçalho
        foreach (self::CABECALHOS as $col => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($col + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
        }

        // Linha exemplo
        foreach (self::LINHA_EXEMPLO as $col => $value) {
            $colLetter = Coordinate::stringFromColumnIndex($col + 1);
            $sheet->setCellValue("{$colLetter}2", $value);
        }

        // Salva em temp
        $tmpPath = tempnam(sys_get_temp_dir(), 'paciente360_template_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpPath);

        return response()->download($tmpPath, 'template_importacao_pacientes.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
