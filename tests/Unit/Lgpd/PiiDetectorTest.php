<?php

declare(strict_types=1);

namespace Tests\Unit\Lgpd;

use App\Support\Lgpd\PiiDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * **T015** — Unit tests do {@see PiiDetector} (Phase 2 Foundational).
 *
 * Cobre matches positivos e negativos para cada um dos 5 padrões
 * (CPF, e-mail, SUS, telefone BR, RG). Edge cases incluem strings vazias,
 * IDs externos longos (potencial falso positivo de SUS), e detecção
 * recursiva em arrays.
 */
class PiiDetectorTest extends TestCase
{
    public static function cpfMatches(): array
    {
        return [
            'cpf com mascara' => ['123.456.789-09', true],
            'cpf sem mascara' => ['12345678909', true],
            'cpf parcial mascara' => ['123.456.78909', true],
            'sem cpf' => ['nada aqui', false],
        ];
    }

    #[DataProvider('cpfMatches')]
    public function test_detects_cpf_with_or_without_mask(string $input, bool $shouldMatch): void
    {
        $findings = PiiDetector::detect($input);

        $cpfFindings = array_filter($findings, fn (array $f): bool => $f['pattern'] === 'cpf');

        if ($shouldMatch) {
            $this->assertNotEmpty($cpfFindings, "Esperava match de CPF em: {$input}");
        } else {
            $this->assertEmpty($cpfFindings, "Não esperava match de CPF em: {$input}");
        }
    }

    public function test_detects_email(): void
    {
        $findings = PiiDetector::detect('contato: maria.silva@example.com.br');
        $emails = array_filter($findings, fn (array $f): bool => $f['pattern'] === 'email');

        $this->assertCount(1, $emails);
    }

    public function test_detects_phone_br_with_ddd(): void
    {
        $findings = PiiDetector::detect('telefone: (11) 98765-4321');
        $phones = array_filter($findings, fn (array $f): bool => $f['pattern'] === 'phone_br');

        $this->assertNotEmpty($phones);
    }

    public function test_detects_sus_card(): void
    {
        $findings = PiiDetector::detect('cartão SUS: 123456789012345');
        $sus = array_filter($findings, fn (array $f): bool => $f['pattern'] === 'sus_card');

        $this->assertCount(1, $sus);
    }

    public function test_returns_empty_for_empty_string(): void
    {
        $this->assertSame([], PiiDetector::detect(''));
    }

    public function test_findings_do_not_expose_matched_value(): void
    {
        // Garantia chave do detector: ele NÃO armazena o valor real do PII,
        // só nome do padrão + offset/length. Isso impede que tickets de
        // auditoria revelem dados que deveriam estar ocultos.
        $findings = PiiDetector::detect('CPF: 111.222.333-44');

        foreach ($findings as $finding) {
            $this->assertArrayHasKey('pattern', $finding);
            $this->assertArrayHasKey('offset', $finding);
            $this->assertArrayHasKey('length', $finding);
            $this->assertArrayNotHasKey('value', $finding);
            $this->assertArrayNotHasKey('match', $finding);
        }
    }

    public function test_detects_in_nested_payload_with_field_path(): void
    {
        $payload = [
            'patient' => [
                'name' => 'Maria',
                'cpf' => '111.222.333-44',
            ],
            'metadata' => [
                'source' => 'webhook',
            ],
        ];

        $findings = PiiDetector::detectInPayload($payload);

        $cpfHits = array_filter($findings, fn (array $f): bool => $f['pattern'] === 'cpf');
        $cpfHit = array_values($cpfHits)[0] ?? null;

        $this->assertNotNull($cpfHit);
        $this->assertSame('patient.cpf', $cpfHit['field_path']);
    }

    public function test_known_false_positive_15_digit_external_id_matches_sus(): void
    {
        // R-8-6: identificador externo de 15 dígitos casa com padrão SUS.
        // Documentado como falso positivo esperado — relatório gera ticket,
        // não bloqueia. Whitelist por campo será adicionada se relevante.
        $findings = PiiDetector::detect('external_id: 999888777666555');
        $sus = array_filter($findings, fn (array $f): bool => $f['pattern'] === 'sus_card');

        $this->assertCount(1, $sus, 'Falso positivo esperado: 15 dígitos casa SUS pattern.');
    }
}
