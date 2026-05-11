<?php

namespace Tests\Unit\Support\Tags;

use App\Support\Tags\TagNormalizer;
use Tests\TestCase;

/**
 * T038 — Testes unit do TagNormalizer.
 */
class TagNormalizerTest extends TestCase
{
    /** @test */
    public function test_accent_insensitive(): void
    {
        $this->assertSame(
            TagNormalizer::normalize('Diabético'),
            TagNormalizer::normalize('diabetico')
        );

        $this->assertSame('diabetico', TagNormalizer::normalize('Diabético'));
    }

    /** @test */
    public function test_case_insensitive(): void
    {
        $this->assertSame('vip', TagNormalizer::normalize('VIP'));
        $this->assertSame('vip', TagNormalizer::normalize('Vip'));
    }

    /** @test */
    public function test_collapses_multiple_spaces(): void
    {
        $this->assertSame('alta prioridade', TagNormalizer::normalize('  Alta   Prioridade  '));
    }

    /** @test */
    public function test_compound_accents(): void
    {
        $this->assertSame('reuniao', TagNormalizer::normalize('Reunião'));
        $this->assertSame('saude', TagNormalizer::normalize('Saúde'));
    }

    /** @test */
    public function test_system_prefix_preserved(): void
    {
        $this->assertSame('sys:vip', TagNormalizer::normalize('sys:VIP'));
        $this->assertSame('sys:plano basico', TagNormalizer::normalize('sys:Plano  Básico'));
    }

    /** @test */
    public function test_empty_returns_empty(): void
    {
        $this->assertSame('', TagNormalizer::normalize(''));
        $this->assertSame('', TagNormalizer::normalize('   '));
    }
}
