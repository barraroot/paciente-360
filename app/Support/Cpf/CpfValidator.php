<?php

namespace App\Support\Cpf;

/**
 * T036 — Validador e formatador de CPF (Cadastro de Pessoa Física — BR).
 *
 * Algoritmo dos dígitos verificadores conforme Receita Federal:
 *  - DV1: soma dos 9 primeiros dígitos * pesos [10..2] → mod 11.
 *  - DV2: soma dos 10 primeiros dígitos * pesos [11..2] → mod 11.
 *
 * Rejeita CPFs com todos os dígitos iguais (caso degenerado que satisfaz
 * o algoritmo matematicamente mas é fraude — `00000000000`, `11111111111`).
 *
 * @see specs/002-crm-pacientes/spec.md AC-3.1.2
 */
final class CpfValidator
{
    /**
     * Remove qualquer caractere não-numérico — útil para aceitar tanto
     * `12345678909` quanto `123.456.789-09`.
     */
    public static function canonicalize(string $raw): string
    {
        return preg_replace('/\D+/', '', $raw) ?? '';
    }

    /**
     * Valida um CPF (com ou sem máscara). Retorna `false` em qualquer
     * caso degenerado (vazio, ≠ 11 dígitos, todos iguais, DV inválido).
     */
    public static function isValid(string $raw): bool
    {
        $digits = self::canonicalize($raw);

        if (strlen($digits) !== 11) {
            return false;
        }

        // CPFs com todos os dígitos iguais (`00000000000`, `11111111111`,
        // ..., `99999999999`) passam no algoritmo matemático mas são
        // inválidos por convenção da RFB.
        if (preg_match('/^(\d)\1{10}$/', $digits) === 1) {
            return false;
        }

        return self::digitoVerificador($digits, 9) === (int) $digits[9]
            && self::digitoVerificador($digits, 10) === (int) $digits[10];
    }

    /**
     * Formata um CPF (em qualquer forma) como `000.000.000-00`.
     * Se a entrada não tiver 11 dígitos, retorna a string como está.
     */
    public static function format(string $raw): string
    {
        $digits = self::canonicalize($raw);

        if (strlen($digits) !== 11) {
            return $raw;
        }

        return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9, 2);
    }

    /**
     * Calcula o dígito verificador esperado para a posição informada
     * (`$length` = 9 → DV1, `$length` = 10 → DV2).
     */
    private static function digitoVerificador(string $digits, int $length): int
    {
        $sum = 0;
        $weight = $length + 1;

        for ($i = 0; $i < $length; $i++) {
            $sum += ((int) $digits[$i]) * $weight;
            $weight--;
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
