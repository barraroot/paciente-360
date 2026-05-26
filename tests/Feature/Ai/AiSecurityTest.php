<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Model\Models\AiModel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * Polish / G2 / G12 / Princípio VII — cada endpoint exige a ability correta,
 * o endpoint de markdown é rate-limited e o back-end sanitiza mesmo via API direta.
 */
final class AiSecurityTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('ai-security', 'admin-clinica');
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    private function actAsRolelessUser(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($user, ['*']);
    }

    public function test_endpoints_require_ai_abilities(): void
    {
        $this->actAsRolelessUser();

        $cases = [
            ['post', '/ai/personas', ['name' => 'x', 'ai_model_id' => 1, 'markdown_content' => '# y']],
            ['post', '/ai/knowledge-bases', ['name' => 'x', 'markdown_content' => '# y']],
            ['post', '/ai/guardrails', ['name' => 'x', 'markdown_content' => '# y']],
            ['put', '/ai/persona-channels', ['cells' => []]],
            ['get', '/ai/execution-logs', []],
            ['post', '/ai/markdown/validate', ['type' => 'persona', 'content' => '# y']],
        ];

        foreach ($cases as [$method, $path, $payload]) {
            $response = $this->withHeaders($this->headers())
                ->json(strtoupper($method), $this->tenantUrl($this->tenant, $path), $payload);

            $this->assertSame(403, $response->getStatusCode(), "{$method} {$path} deveria exigir permissão");
        }
    }

    public function test_markdown_validate_route_is_rate_limited(): void
    {
        $route = Route::getRoutes()->getByName('ai.markdown.validate');

        $this->assertNotNull($route);
        $this->assertContains('throttle:60,1', $route->gatherMiddleware());
    }

    public function test_backend_sanitizes_even_via_direct_api_call(): void
    {
        // Usuário autorizado, mas payload malicioso direto na API.
        $response = $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/ai/markdown/validate'),
            ['type' => 'persona', 'content' => '<script>steal()</script><img src=x onerror=alert(1)> ok'],
        );

        $response->assertOk()->assertJsonPath('data.has_unsafe', true);
        $sanitized = $response->json('data.sanitized');
        $this->assertStringNotContainsString('<script', $sanitized);
        $this->assertStringNotContainsString('onerror', $sanitized);
    }

    public function test_persona_markdown_is_sanitized_on_store(): void
    {
        $response = $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/ai/personas'),
            [
                'ai_model_id' => AiModel::factory()->create()->id,
                'name' => 'Persona XSS',
                'markdown_content' => "# Ok\n\n<script>alert('x')</script> conteúdo.",
                'model_settings' => ['temperature' => 0.5, 'max_tokens' => 1024],
            ],
        );

        $response->assertCreated();
        $this->assertStringNotContainsString('<script>', $response->json('data.markdown_content'));
    }
}
