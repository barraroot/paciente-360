<?php

namespace App\Support\Telefone;

use InvalidArgumentException;

/**
 * T037 — Normaliza e formata telefones brasileiros (E.164 ↔ display).
 *
 * Regras BR:
 *  - DDI: `55`.
 *  - DDD: 2 dígitos.
 *  - Móvel: 9 dígitos locais (começa com `9`); total = `+55XXYYYYYYYYY` (13 dígitos com `+`).
 *  - Fixo: 8 dígitos locais; total = `+55XXYYYYYYYY` (12 dígitos com `+`).
 *
 * `normalize()` aceita entrada bagunçada (`(31) 99999-9999`, `5531999999999`,
 * `+55 31 9999-9999`) e retorna E.164. `format()` faz o caminho inverso
 * para exibição em pt-BR (`(31) 99999-9999` / `(31) 9999-9999`).
 */
final class TelefoneNormalizer
{
    /**
     * Retorna o E.164 (`+55XXYYYYYYYYY` para celular ou `+55XXYYYYYYYY` para fixo).
     *
     * @throws InvalidArgumentException quando o número não pode ser normalizado.
     */
    public static function normalize(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            throw new InvalidArgumentException('Telefone vazio.');
        }

        // Se já vem com DDI 55 (12 ou 13 dígitos), deixa.
        // Caso contrário, prepend 55.
        if (! str_starts_with($digits, '55') || strlen($digits) < 12) {
            $digits = '55'.$digits;
        }

        // Validação de tamanho: 12 (fixo) ou 13 (celular).
        $length = strlen($digits);
        if ($length !== 12 && $length !== 13) {
            throw new InvalidArgumentException(
                "Telefone com tamanho inválido após canonicalização: {$digits}"
            );
        }

        return '+'.$digits;
    }

    /**
     * Formata um E.164 BR para display pt-BR.
     *  - Celular: `(31) 99999-9999`.
     *  - Fixo:    `(31) 9999-9999`.
     */
    public static function format(string $e164): string
    {
        $digits = preg_replace('/\D+/', '', $e164) ?? '';

        if (! str_starts_with($digits, '55')) {
            return $e164;
        }

        $local = substr($digits, 2);

        if (strlen($local) === 11) {
            // celular
            $ddd = substr($local, 0, 2);
            $first = substr($local, 2, 5);
            $second = substr($local, 7, 4);

            return "({$ddd}) {$first}-{$second}";
        }

        if (strlen($local) === 10) {
            // fixo
            $ddd = substr($local, 0, 2);
            $first = substr($local, 2, 4);
            $second = substr($local, 6, 4);

            return "({$ddd}) {$first}-{$second}";
        }

        return $e164;
    }
}
