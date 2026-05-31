<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\Mcp;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * T060 — Listagem de capabilities MCP.
 * FR-045: servidor MCP expõe 6+ capabilities com nomes, descrições PT-BR e schemas.
 * FR-046: listagem também exige autenticação.
 */
final class McpCapabilitiesListingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private string $validToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();

        if (! \Schema::hasTable('personal_access_tokens')) {
            $this->markTestSkipped('personal_access_tokens table não existe');
        }

        // Cria token válido
        $raw = Str::random(40);
        $hash = hash('sha256', $raw);
        $token = new PersonalAccessToken;
        $token->forceFill([
            'tokenable_type' => 'App\\Models\\Tenant',
            'tokenable_id' => $this->tenant->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'test-token',
            'token' => $hash,
            'abilities' => ['mcp.invoke'],
        ])->save();
        $this->validToken = $token->id.'|'.$raw;
    }

    public function test_tools_list_without_auth_returns_401(): void
    {
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ]);

        $response->assertStatus(401);
    }

    public function test_tools_list_with_valid_auth_returns_all_capabilities(): void
    {
        $response = $this->postJson(
            '/mcp',
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/list',
            ],
            ['Authorization' => "Bearer {$this->validToken}"]
        );

        $response->assertStatus(200);
        $tools = $response->json('result.tools');

        $this->assertIsArray($tools);
        $toolNames = array_column($tools, 'name');

        // Verifica que as 6 capabilities obrigatórias estão presentes
        $expectedTools = [
            'get-clinic-info',
            'list-professionals',
            'get-availability',
            'get-current-patient',
            'create-or-find-lead',
            'hold-slot',
        ];

        foreach ($expectedTools as $tool) {
            $this->assertContains($tool, $toolNames, "Capability '{$tool}' não encontrada na listagem");
        }
    }

    public function test_capability_descriptions_are_in_pt_br(): void
    {
        $response = $this->postJson(
            '/mcp',
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/list',
            ],
            ['Authorization' => "Bearer {$this->validToken}"]
        );

        $response->assertStatus(200);
        $tools = $response->json('result.tools');

        // Verifica que cada capability tem descrição em PT-BR
        $ptBrWords = ['consulta', 'profissionais', 'horários', 'lead', 'reserva', 'paciente', 'clínica', 'disponibilidade'];

        foreach ($tools as $tool) {
            $description = strtolower($tool['description'] ?? '');
            $hasPtBr = false;
            foreach ($ptBrWords as $word) {
                if (str_contains($description, $word)) {
                    $hasPtBr = true;
                    break;
                }
            }
            $this->assertTrue($hasPtBr, "Capability '{$tool['name']}' não tem descrição em PT-BR: {$tool['description']}");
        }
    }

    public function test_each_capability_has_input_schema(): void
    {
        $response = $this->postJson(
            '/mcp',
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/list',
            ],
            ['Authorization' => "Bearer {$this->validToken}"]
        );

        $response->assertStatus(200);
        $tools = $response->json('result.tools');

        foreach ($tools as $tool) {
            $this->assertArrayHasKey('inputSchema', $tool, "Capability '{$tool['name']}' não tem inputSchema");
            $this->assertIsArray($tool['inputSchema']);
        }
    }
}
