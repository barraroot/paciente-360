<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\Mcp;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * T058 — Autenticação MCP: validação de token, ability e tenant.
 * FR-046: servidor MCP autentica todas as chamadas.
 */
final class McpAuthTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        if (! \Schema::hasTable('personal_access_tokens')) {
            $this->markTestSkipped('personal_access_tokens table não existe');
        }
    }

    public function test_mcp_call_without_bearer_token_returns_401_auth_missing(): void
    {
        $response = $this->postJson('/mcp', ['tools' => ['list']]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'auth_missing']);
    }

    public function test_mcp_call_with_invalid_token_returns_401_auth_invalid(): void
    {
        $response = $this->postJson(
            '/mcp',
            ['tools' => ['list']],
            ['Authorization' => 'Bearer invalid.token.here']
        );

        $response->assertStatus(401);
        $response->assertJson(['error' => 'auth_invalid']);
    }

    public function test_mcp_call_without_mcp_invoke_ability_returns_403_permission_denied(): void
    {
        // Cria token sem a ability 'mcp.invoke'
        $raw = Str::random(40);
        $hash = hash('sha256', $raw);
        $token = new PersonalAccessToken;
        $token->forceFill([
            'tokenable_type' => 'App\\Models\\Tenant',
            'tokenable_id' => $this->tenant->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'test-no-ability',
            'token' => $hash,
            'abilities' => ['other-ability'],
        ])->save();
        $plainTextToken = $token->id.'|'.$raw;

        $response = $this->postJson(
            '/mcp',
            ['tools' => ['list']],
            ['Authorization' => "Bearer {$plainTextToken}"]
        );

        $response->assertStatus(403);
        $response->assertJson(['error' => 'permission_denied']);
    }

    public function test_mcp_call_with_token_lacking_tenant_id_returns_403_tenant_required(): void
    {
        // Cria token SEM tenant_id (simula token de user, não de MCP)
        $raw = Str::random(40);
        $hash = hash('sha256', $raw);
        $token = new PersonalAccessToken;
        $token->forceFill([
            'tokenable_type' => 'App\\Models\\Tenant',
            'tokenable_id' => $this->tenant->id,
            'tenant_id' => null, // Sem tenant_id
            'name' => 'test-no-tenant',
            'token' => $hash,
            'abilities' => ['mcp.invoke'],
        ])->save();
        $plainTextToken = $token->id.'|'.$raw;

        $response = $this->postJson(
            '/mcp',
            ['tools' => ['list']],
            ['Authorization' => "Bearer {$plainTextToken}"]
        );

        $response->assertStatus(403);
        $response->assertJson(['error' => 'tenant_required']);
    }
}
