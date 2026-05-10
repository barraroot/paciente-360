<?php

namespace Tests\Unit\Cnpj;

use App\Support\Cnpj\CnpjValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * **T143 (unit)** — algoritmo de validação de DV CNPJ (Receita Federal).
 *
 * @see App\Support\Cnpj\CnpjValidator
 */
class CnpjValidatorTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function validCnpjsProvider(): array
    {
        return [
            'clinica-foo' => ['11222333000181'],
            'pet-shop' => ['04252011000110'],
            'industria' => ['11444777000161'],
        ];
    }

    #[DataProvider('validCnpjsProvider')]
    public function test_valid_cnpj_returns_true(string $cnpj): void
    {
        $this->assertTrue(CnpjValidator::isValid($cnpj));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidDvProvider(): array
    {
        return [
            'wrong-d1' => ['11222333000111'],
            'wrong-d2' => ['11222333000182'],
            'random' => ['12345678000199'],
        ];
    }

    #[DataProvider('invalidDvProvider')]
    public function test_invalid_dv_returns_false(string $cnpj): void
    {
        $this->assertFalse(CnpjValidator::isValid($cnpj));
    }

    public function test_empty_string_returns_false(): void
    {
        $this->assertFalse(CnpjValidator::isValid(''));
    }

    public function test_short_string_returns_false(): void
    {
        $this->assertFalse(CnpjValidator::isValid('1234'));
    }

    public function test_all_zeros_returns_false(): void
    {
        // CNPJ "00000000000000" passa o cálculo de DV mas é universalmente
        // inválido (Receita rejeita). Validator deve recusar.
        $this->assertFalse(CnpjValidator::isValid('00000000000000'));
    }

    public function test_non_digit_input_returns_false(): void
    {
        // Validator espera dígitos; canonicalização é responsabilidade do caller.
        $this->assertFalse(CnpjValidator::isValid('11.222.333/0001-81'));
    }
}
