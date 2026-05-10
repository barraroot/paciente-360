<?php

namespace App\Support\Cnpj;

/**
 * Validador de CNPJ — algoritmo padrão Receita Federal (módulo 11).
 *
 * Recebe a string canônica (apenas dígitos, 14 caracteres). Não faz
 * canonicalização — o caller é responsável por remover máscara antes.
 *
 * Pesos:
 *  - 1º dígito verificador: 5,4,3,2,9,8,7,6,5,4,3,2 (12 posições).
 *  - 2º dígito verificador: 6,5,4,3,2,9,8,7,6,5,4,3,2 (13 posições).
 *
 * Regras adicionais (boas práticas):
 *  - Rejeita string vazia ou comprimento != 14.
 *  - Rejeita CNPJ com todos os dígitos iguais (`00000000000000`,
 *    `11111111111111`, ...) — passa o cálculo mas é universalmente
 *    inválido na Receita.
 *
 * @see specs/001-fundacao-multitenant/spec.md — FR-002 (validação CNPJ)
 */
final class CnpjValidator
{
    /** @var list<int> */
    private const WEIGHTS_FIRST_DIGIT = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    /** @var list<int> */
    private const WEIGHTS_SECOND_DIGIT = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    /**
     * Verifica se o CNPJ informado é válido segundo o algoritmo de DV.
     */
    public static function isValid(string $digits): bool
    {
        if (strlen($digits) !== 14) {
            return false;
        }

        if (preg_match('/^\d{14}$/', $digits) !== 1) {
            return false;
        }

        // Rejeita sequências triviais (todos iguais).
        if (preg_match('/^(\d)\1{13}$/', $digits) === 1) {
            return false;
        }

        $base = substr($digits, 0, 12);

        $firstDv = self::computeDigit($base, self::WEIGHTS_FIRST_DIGIT);
        $secondDv = self::computeDigit($base.$firstDv, self::WEIGHTS_SECOND_DIGIT);

        return $digits === $base.$firstDv.$secondDv;
    }

    /**
     * Calcula um dígito verificador via módulo 11 sobre os pesos informados.
     *
     * @param list<int> $weights
     */
    private static function computeDigit(string $base, array $weights): int
    {
        $sum = 0;

        foreach ($weights as $i => $weight) {
            $sum += ((int) $base[$i]) * $weight;
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
