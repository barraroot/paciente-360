<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * US8 / G12 / SC-009 / SC-010 — o back-end sanitiza Markdown: remove script,
 * HTML embutido e handlers de evento, mesmo quando chamado direto na API.
 */
final class MarkdownValidateTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('ai-markdown', 'admin-clinica');
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    public function test_strips_script_and_flags_unsafe(): void
    {
        $response = $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/ai/markdown/validate'),
            ['type' => 'persona', 'content' => "# Olá\n\n<script>alert('x')</script> texto seguro."],
        );

        $response->assertOk()
            ->assertJsonPath('data.has_unsafe', true);

        $this->assertStringNotContainsString('<script>', $response->json('data.sanitized'));
        $this->assertNotEmpty($response->json('data.warnings'));
    }

    public function test_strips_event_handlers_and_raw_html(): void
    {
        $response = $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/ai/markdown/validate'),
            ['type' => 'guardrail', 'content' => '<div onclick="evil()">x</div>'],
        );

        $response->assertOk();
        $sanitized = $response->json('data.sanitized');
        $this->assertStringNotContainsString('onclick', $sanitized);
        $this->assertStringNotContainsString('<div', $sanitized);
    }

    public function test_clean_markdown_is_unchanged(): void
    {
        $clean = "# Título\n\n**Negrito** e _itálico_.\n\n- item 1\n- item 2";

        $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/ai/markdown/validate'),
            ['type' => 'knowledge_base', 'content' => $clean],
        )->assertOk()
            ->assertJsonPath('data.has_unsafe', false)
            ->assertJsonPath('data.sanitized', $clean);
    }

    public function test_requires_content(): void
    {
        $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/ai/markdown/validate'),
            ['type' => 'persona'],
        )->assertStatus(422)->assertJsonValidationErrors('content');
    }

    public function test_requires_management_permission(): void
    {
        // 'financeiro' não tem nenhuma ability de gestão de conteúdo de IA.
        $noPerm = $this->userForRole($this->tenant, 'financeiro');
        Sanctum::actingAs($noPerm, ['*']);

        $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/ai/markdown/validate'),
            ['type' => 'persona', 'content' => '# x'],
        )->assertForbidden();
    }
}
