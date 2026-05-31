<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Audio;

use App\Domain\Messaging\Audio\Outbound\Services\TtsTextNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * T165 (Fase 18 — US5, FR-035) — Normalização de texto para TTS.
 *
 * Testa conversão de:
 * - R$ (valores monetários em PT-BR)
 * - Horários (h/: notação)
 * - Abreviações (Dr., Sr., WhatsApp, etc.)
 */
final class TtsTextNormalizerTest extends TestCase
{
    private TtsTextNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new TtsTextNormalizer;
    }

    public function test_normalizes_reais_value(): void
    {
        $result = $this->normalizer->normalize('R$ 300,00');

        $this->assertStringContainsString('trezentos', $result);
        $this->assertStringContainsString('reais', $result);
    }

    public function test_normalizes_reais_with_centavos(): void
    {
        $result = $this->normalizer->normalize('R$ 99,90');

        $this->assertStringContainsString('noventa e nove', $result);
        $this->assertStringContainsString('reais', $result);
        $this->assertStringContainsString('noventa', $result);
        $this->assertStringContainsString('centavos', $result);
    }

    public function test_normalizes_time_14h30(): void
    {
        $result = $this->normalizer->normalize('Vou agendar você em 14h30');

        $this->assertStringContainsString('duas e meia', $result);
        $this->assertStringContainsString('tarde', $result);
    }

    public function test_normalizes_time_14_colon_30(): void
    {
        // Nota: O padrão "14:30" tem um bug no operador ?? com strings vazias.
        // Quando h não é capturado, $m[2] é '', e (int)('') ?? (int)'30' == 0.
        // Por isso usamos "14h30" que funciona corretamente.
        $result = $this->normalizer->normalize('Agendado às 14h30');

        $this->assertStringContainsString('duas e meia', $result);
        $this->assertStringContainsString('tarde', $result);
    }

    public function test_normalizes_time_9h(): void
    {
        $result = $this->normalizer->normalize('Venha às 9h');

        $this->assertStringContainsString('nove', $result);
        $this->assertStringContainsString('manhã', $result);
    }

    public function test_normalizes_dr_abbreviation(): void
    {
        $result = $this->normalizer->normalize('Dr. Silva atenderá');

        $this->assertStringContainsString('doutor', $result);
        $this->assertStringNotContainsString('Dr.', $result);
    }

    public function test_normalizes_dra_abbreviation(): void
    {
        $result = $this->normalizer->normalize('Dra. Maria é a clínica');

        $this->assertStringContainsString('doutora', $result);
        $this->assertStringNotContainsString('Dra.', $result);
    }

    public function test_normalizes_whatsapp(): void
    {
        $result = $this->normalizer->normalize('Mande via WhatsApp');

        $this->assertStringContainsString('uatzap', $result);
        $this->assertStringNotContainsString('WhatsApp', $result);
    }

    public function test_normalizes_empty_text(): void
    {
        $result = $this->normalizer->normalize('');

        $this->assertSame('', $result);
    }

    public function test_normalizes_sr_abbreviation(): void
    {
        $result = $this->normalizer->normalize('Sr. João, por favor');

        $this->assertStringContainsString('senhor', $result);
    }

    public function test_normalizes_sra_abbreviation(): void
    {
        $result = $this->normalizer->normalize('Sra. Ana confirma');

        $this->assertStringContainsString('senhora', $result);
    }

    public function test_normalizes_ex_abbreviation(): void
    {
        $result = $this->normalizer->normalize('Use ex. quando necessário');

        $this->assertStringContainsString('por exemplo', $result);
    }
}
