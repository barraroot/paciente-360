<?php

declare(strict_types=1);

namespace Tests\Unit\Lgpd;

use App\Support\Lgpd\PiiScrubber;
use Tests\TestCase;

/**
 * **T079 (Fase 8 — Lote A US-13.3)** — AC-13.3.7 / FR-13.12.
 *
 * Valida que {@see PiiScrubber} aplica máscaras em strings e arrays nested,
 * preservando estrutura sem expor PII. Foco em integração com Sentry
 * `before_send` callback — mas o teste valida o scrubber isolado.
 */
class PiiScrubberSentryIntegrationTest extends TestCase
{
    public function test_scrub_replaces_cpf_with_masked_placeholder(): void
    {
        $input = 'Paciente CPF: 123.456.789-09 encontrado.';
        $output = PiiScrubber::scrub($input);

        $this->assertStringNotContainsString('123.456.789-09', $output);
        $this->assertStringContainsString('***.***.***-**', $output);
    }

    public function test_scrub_replaces_phone_and_email(): void
    {
        $input = 'Contato: (11) 98765-4321 / maria@example.com';
        $output = PiiScrubber::scrub($input);

        $this->assertStringNotContainsString('98765-4321', $output);
        $this->assertStringNotContainsString('maria@example.com', $output);
        $this->assertStringContainsString('<phone>', $output);
        $this->assertStringContainsString('<email>', $output);
    }

    public function test_scrub_recursive_on_nested_arrays(): void
    {
        $payload = [
            'patient' => [
                'name' => 'Maria',
                'cpf' => '987.654.321-00',
                'contact' => [
                    'phone' => '11999998888',
                    'email' => 'maria@example.com',
                ],
            ],
            'metadata' => [
                'source' => 'web',
                'ts' => '2026-05-22T10:00:00Z',
            ],
        ];

        $scrubbed = PiiScrubber::scrub($payload);

        $this->assertSame('***.***.***-**', $scrubbed['patient']['cpf']);
        $this->assertSame('<phone>', $scrubbed['patient']['contact']['phone']);
        $this->assertSame('<email>', $scrubbed['patient']['contact']['email']);
        // Metadata preservada inalterada.
        $this->assertSame('web', $scrubbed['metadata']['source']);
        $this->assertSame('2026-05-22T10:00:00Z', $scrubbed['metadata']['ts']);
    }

    public function test_scrub_preserves_non_string_non_array_values(): void
    {
        $payload = [
            'count' => 42,
            'price' => 99.50,
            'active' => true,
            'null_field' => null,
        ];

        $scrubbed = PiiScrubber::scrub($payload);

        $this->assertSame(42, $scrubbed['count']);
        $this->assertSame(99.50, $scrubbed['price']);
        $this->assertTrue($scrubbed['active']);
        $this->assertNull($scrubbed['null_field']);
    }

    public function test_scrub_handles_empty_input_safely(): void
    {
        $this->assertSame('', PiiScrubber::scrub(''));
        $this->assertSame([], PiiScrubber::scrub([]));
        $this->assertNull(PiiScrubber::scrub(null));
    }

    public function test_scrub_sentry_payload_format(): void
    {
        // Sentry `before_send` recebe um array com chaves 'message', 'tags',
        // 'extra', 'breadcrumbs'. Validamos que o scrubber preserva essa estrutura.
        $sentryEvent = [
            'message' => 'Erro ao processar paciente CPF 111.222.333-44',
            'tags' => ['tenant_id' => '5', 'env' => 'production'],
            'extra' => [
                'phone' => '11988887777',
                'request' => 'GET /api/v1/pacientes/42',
            ],
        ];

        $scrubbed = PiiScrubber::scrub($sentryEvent);

        $this->assertStringNotContainsString('111.222.333-44', $scrubbed['message']);
        $this->assertSame('<phone>', $scrubbed['extra']['phone']);
        $this->assertSame('production', $scrubbed['tags']['env']);
        $this->assertSame('GET /api/v1/pacientes/42', $scrubbed['extra']['request']);
    }
}
