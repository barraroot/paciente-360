<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use Tests\TestCase;

/**
 * **T243 (Fase 8 — Lote D US-11.2)** — OpenAPI 3.0 spec validation.
 *
 * Quando `public/docs/api/v1.yaml` existir (gerado por `scribe:generate`),
 * o test valida que o documento tem estrutura mínima OpenAPI 3.0:
 *   - openapi: "3.0.x" ou "3.1.x"
 *   - info {title, version}
 *   - paths não-vazio
 *
 * Skip se o arquivo não existe — gerar via `php artisan scribe:generate`.
 */
class OpenApiSpecValidatesTest extends TestCase
{
    public function test_openapi_spec_is_valid_when_generated(): void
    {
        $path = public_path('docs/api/v1.yaml');

        if (! file_exists($path)) {
            $this->markTestSkipped('Spec OpenAPI ainda não gerado — rode `php artisan scribe:generate`.');
        }

        $content = file_get_contents($path);
        $this->assertNotFalse($content);

        // Validação minimal — não usa biblioteca externa de schema OpenAPI.
        $this->assertMatchesRegularExpression('/openapi:\s*[\'"]?3\.(0|1)\./', $content);
        $this->assertStringContainsString('info:', $content);
        $this->assertStringContainsString('paths:', $content);

        // Verifica que os 6 recursos do escopo Q14 estão documentados.
        foreach (['/patients', '/appointments', '/messages', '/prescriptions', '/appointment-types', '/professionals'] as $resource) {
            $this->assertStringContainsString($resource, $content, "Recurso {$resource} ausente do spec.");
        }
    }
}
