<?php

namespace Tests\Unit\Support\Telefone;

use App\Support\Telefone\TelefoneNormalizer;
use Tests\TestCase;

/**
 * T037 — Testes unit do TelefoneNormalizer.
 */
class TelefoneNormalizerTest extends TestCase
{
    /** @test */
    public function test_celular_masked_to_e164(): void
    {
        $this->assertSame('+5531999999999', TelefoneNormalizer::normalize('(31) 99999-9999'));
    }

    /** @test */
    public function test_celular_only_digits_to_e164(): void
    {
        $this->assertSame('+5531999999999', TelefoneNormalizer::normalize('31999999999'));
    }

    /** @test */
    public function test_celular_with_ddi_preserves(): void
    {
        $this->assertSame('+5531999999999', TelefoneNormalizer::normalize('+55 31 99999-9999'));
        $this->assertSame('+5531999999999', TelefoneNormalizer::normalize('5531999999999'));
    }

    /** @test */
    public function test_fixo_masked_to_e164(): void
    {
        $this->assertSame('+553133334444', TelefoneNormalizer::normalize('(31) 3333-4444'));
    }

    /** @test */
    public function test_format_celular_pt_br(): void
    {
        $this->assertSame('(31) 99999-9999', TelefoneNormalizer::format('+5531999999999'));
    }

    /** @test */
    public function test_format_fixo_pt_br(): void
    {
        $this->assertSame('(31) 3333-4444', TelefoneNormalizer::format('+553133334444'));
    }

    /** @test */
    public function test_empty_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TelefoneNormalizer::normalize('');
    }

    /** @test */
    public function test_invalid_length_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TelefoneNormalizer::normalize('123');
    }
}
