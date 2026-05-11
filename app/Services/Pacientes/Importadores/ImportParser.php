<?php

namespace App\Services\Pacientes\Importadores;

/**
 * T182 — Interface para parsers de arquivo de importação de pacientes.
 *
 * Cada implementação (CSV, XLSX) normaliza cabeçalhos para snake_case e
 * itera linhas como Generator para minimizar uso de memória.
 */
interface ImportParser
{
    /**
     * Itera sobre as linhas de dados (excluindo cabeçalho).
     *
     * @return \Generator<int, array{linha: int, dados: array<string, string>}>
     */
    public function iterate(): \Generator;

    /**
     * Contagem total de linhas de dados (excluindo cabeçalho).
     * Pode ser null se a contagem for custosa (ex.: arquivo grande).
     */
    public function totalLinhas(): ?int;

    /**
     * Cabeçalhos normalizados em snake_case.
     *
     * @return list<string>
     */
    public function cabecalhos(): array;
}
