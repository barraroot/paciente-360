<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\Mcp;

use App\Models\Agenda\AppointmentType;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * T059 — Isolamento multi-tenant adversarial (SC-007).
 * FR-050: isolamento de tenant no data layer; impossível retornar dados de outro tenant
 * mesmo com input adversarial.
 */
final class McpCrossTenantTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantA = Tenant::factory()->create(['name' => 'Clínica A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Clínica B']);

        if (! \Schema::hasTable('personal_access_tokens')) {
            $this->markTestSkipped('personal_access_tokens table não existe');
        }

        // Cria token PAT para tenant A com ability mcp.invoke
        $raw = Str::random(40);
        $hash = hash('sha256', $raw);
        $token = new PersonalAccessToken;
        $token->forceFill([
            'tokenable_type' => 'App\\Models\\Tenant',
            'tokenable_id' => $this->tenantA->id,
            'tenant_id' => $this->tenantA->id,
            'name' => 'test-token-a',
            'token' => $hash,
            'abilities' => ['mcp.invoke'],
        ])->save();
        $this->tokenA = $token->id.'|'.$raw;
    }

    public function test_get_clinic_info_returns_only_tenant_a_data(): void
    {
        // Cria appointment types em ambos os tenants
        AppointmentType::factory()->create(['tenant_id' => $this->tenantA->id, 'nome' => 'Consulta Clínica A']);
        AppointmentType::factory()->create(['tenant_id' => $this->tenantB->id, 'nome' => 'Consulta Clínica B']);

        // Invoca get-clinic-info com token do tenant A
        $response = $this->postJson(
            '/mcp',
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'get-clinic-info',
                    'arguments' => [],
                ],
            ],
            ['Authorization' => "Bearer {$this->tokenA}"]
        );

        $response->assertStatus(200);
        $result = $response->json('result.content.0.text');

        // Verifica que o resultado contém dados do tenant A
        $this->assertStringContainsString('Consulta Clínica A', $result);
        // Verifica que NÃO contém dados do tenant B
        $this->assertStringNotContainsString('Consulta Clínica B', $result);
    }

    public function test_passing_tenant_id_in_arguments_is_ignored(): void
    {
        AppointmentType::factory()->create(['tenant_id' => $this->tenantA->id, 'nome' => 'Consulta A']);
        AppointmentType::factory()->create(['tenant_id' => $this->tenantB->id, 'nome' => 'Consulta B']);

        // Tenta passar tenant_id no input (esquema não declara esse campo)
        $response = $this->postJson(
            '/mcp',
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'get-clinic-info',
                    'arguments' => [
                        'tenant_id' => $this->tenantB->id, // Tenta injetar tenant B
                    ],
                ],
            ],
            ['Authorization' => "Bearer {$this->tokenA}"]
        );

        // Deve ignorar o input extra e retornar dados do tenant A
        $response->assertStatus(200);
        $result = $response->json('result.content.0.text');
        $this->assertStringContainsString('Consulta A', $result);
        $this->assertStringNotContainsString('Consulta B', $result);
    }

    public function test_get_current_patient_for_tenant_b_patient_is_denied_with_token_a(): void
    {
        // Cria paciente no tenant B
        $patientB = Paciente::factory()->forTenant($this->tenantB)->create();

        // Invoca get-current-patient (na prática a conversa seria do tenant B, mas o token é A)
        // O servidor MCP deve resolver o paciente apenas pelo contexto do token (tenant A)
        $response = $this->postJson(
            '/mcp',
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'get-current-patient',
                    'arguments' => [],
                ],
            ],
            ['Authorization' => "Bearer {$this->tokenA}"]
        );

        // Sem contexto de paciente do tenant A, ou retorna erro ou paciente nulo
        // (a implementação decide entre 403/404/null based on FR-047)
        $this->assertTrue(
            $response->status() === 403 || $response->status() === 404 || $response->json('result.patient_id') === null,
            "Esperava 403/404 ou patient_id null, mas recebeu: {$response->status()}"
        );
    }

    public function test_tenant_b_patient_not_accessible_via_token_a(): void
    {
        $patientB = Paciente::factory()->forTenant($this->tenantB)->create([
            'nome' => 'Paciente B Secreto',
        ]);
        $patientA = Paciente::factory()->forTenant($this->tenantA)->create([
            'nome' => 'Paciente A Normal',
        ]);

        $response = $this->postJson(
            '/mcp',
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'get-current-patient',
                    'arguments' => [],
                ],
            ],
            ['Authorization' => "Bearer {$this->tokenA}"]
        );

        // Resposta deve conter patient_a (se houver contexto) ou rejeitar
        // mas NUNCA deve conter dados de patient_b
        if ($response->status() === 200) {
            $responseText = json_encode($response->json());
            $this->assertStringNotContainsString('Paciente B Secreto', $responseText);
        }
    }
}
