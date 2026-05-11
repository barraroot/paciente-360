<?php

namespace Tests\Unit\Support\Cpf;

use App\Support\Cpf\CpfValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * T036 — Testes unit do CpfValidator (algoritmo + edge cases).
 */
class CpfValidatorTest extends TestCase
{
    /**
     * @return list<array{0: string}>
     */
    public static function validCpfs(): array
    {
        return [
            ['11144477735'],
            ['529.982.247-25'],
            ['12345678909'],
            ['123.456.789-09'],
            ['98765432100'],
        ];
    }

    /**
     * @return list<array{0: string}>
     */
    public static function invalidCpfs(): array
    {
        return [
            ['11144477736'],  // DV2 errado
            ['529.982.247-26'], // DV2 errado
            ['12345678900'],  // DV inválido
            ['11111111111'],  // todos iguais (fraude clássica)
            ['00000000000'],  // todos zero
        ];
    }

    /**
     * @return list<array{0: string}>
     */
    public static function edgeCases(): array
    {
        return [
            [''],            // vazio
            ['123'],         // muito curto
            ['1234567890123'], // muito longo
            ['abc.def.ghi-jk'], // sem dígitos
        ];
    }

    #[DataProvider('validCpfs')]
    public function test_valid_cpfs_pass(string $cpf): void
    {
        $this->assertTrue(CpfValidator::isValid($cpf));
    }

    #[DataProvider('invalidCpfs')]
    public function test_invalid_cpfs_fail(string $cpf): void
    {
        $this->assertFalse(CpfValidator::isValid($cpf));
    }

    #[DataProvider('edgeCases')]
    public function test_edge_cases_fail(string $cpf): void
    {
        $this->assertFalse(CpfValidator::isValid($cpf));
    }

    public function test_format_pads_with_mask(): void
    {
        $this->assertSame('123.456.789-09', CpfValidator::format('12345678909'));
        $this->assertSame('123.456.789-09', CpfValidator::format('123.456.789-09'));
    }

    public function test_format_returns_raw_when_not_eleven_digits(): void
    {
        $this->assertSame('123', CpfValidator::format('123'));
    }

    public function test_canonicalize_strips_non_digits(): void
    {
        $this->assertSame('12345678909', CpfValidator::canonicalize('123.456.789-09'));
        $this->assertSame('', CpfValidator::canonicalize('abc'));
    }
}
