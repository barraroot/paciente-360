<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\Mcp;

use App\Domain\Ai\Mcp\Sandbox\SandboxContext;
use App\Domain\Scheduling\Models\Slot;
use App\Models\Agenda\AppointmentType;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * T063 — Sandbox neutralization para capabilities de escrita.
 * FR-040/041: modo sandbox executa ciclo completo mas neutraliza efeitos colaterais.
 * FR-053b: create-or-find-lead e hold-slot em sandbox retornam output sintético sem persistir.
 */
final class SandboxNeutralizationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private string $sandboxToken;

    protected function setUp(): void
    {
        parent::setUp();
        SandboxContext::reset();
        $this->tenant = Tenant::factory()->create();

        if (! \Schema::hasTable('personal_access_tokens')) {
            $this->markTestSkipped('personal_access_tokens table não existe');
        }

        // Cria token sandbox
        $raw = Str::random(40);
        $hash = hash('sha256', $raw);
        $token = new PersonalAccessToken;
        $token->forceFill([
            'tokenable_type' => 'App\\Models\\Tenant',
            'tokenable_id' => $this->tenant->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'test-sandbox',
            'token' => $hash,
            'abilities' => ['mcp.invoke', 'mcp.sandbox'],
        ])->save();
        $this->sandboxToken = $token->id.'|'.$raw;
    }

    protected function tearDown(): void
    {
        SandboxContext::reset();
        parent::tearDown();
    }

    public function test_create_or_find_lead_in_sandbox_returns_synthetic_output(): void
    {
        $pacientesAntes = \DB::table('pacientes')->count();

        $response = $this->postJson(
            '/mcp',
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'create-or-find-lead',
                    'arguments' => [],
                ],
            ],
            ['Authorization' => "Bearer {$this->sandboxToken}"]
        );

        $response->assertStatus(200);

        $payload = $this->decodeJsonContent($response);
        $this->assertTrue(
            $payload['sandbox'] ?? false,
            'Output deve trazer flag sandbox=true.',
        );
        $this->assertStringStartsWith(
            'sandbox-',
            (string) ($payload['patient_id'] ?? ''),
            'patient_id sintético deve ser prefixado com sandbox-.',
        );
        $this->assertStringContainsString('SANDBOX', (string) ($payload['text'] ?? ''));

        $this->assertEquals(
            $pacientesAntes,
            \DB::table('pacientes')->count(),
            'Sandbox NÃO pode persistir lead em pacientes (FR-041).',
        );
    }

    public function test_hold_slot_in_sandbox_returns_synthetic_output(): void
    {
        $reservasAntes = \DB::table('slot_reservations')->count();

        $response = $this->postJson(
            '/mcp',
            [
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'hold-slot',
                    'arguments' => [
                        'professional_id' => 999,
                        'appointment_type_id' => 999,
                        'starts_at' => '2099-01-01T10:00:00Z',
                    ],
                ],
            ],
            ['Authorization' => "Bearer {$this->sandboxToken}"]
        );

        $response->assertStatus(200);

        $payload = $this->decodeJsonContent($response);
        $this->assertTrue(
            $payload['sandbox'] ?? false,
            'Output deve trazer flag sandbox=true.',
        );
        $this->assertStringStartsWith(
            'sandbox-',
            (string) ($payload['slot_reservation_id'] ?? ''),
            'slot_reservation_id sintético deve ser prefixado com sandbox-.',
        );
        $this->assertStringContainsString('SANDBOX', (string) ($payload['text'] ?? ''));

        $this->assertEquals(
            $reservasAntes,
            \DB::table('slot_reservations')->count(),
            'Sandbox NÃO pode persistir reserva em slot_reservations (FR-041).',
        );
    }

    /**
     * Capabilities sandbox retornam JSON via Response::json() — o servidor MCP
     * empacota como `content[0].text` (string JSON). Esta helper extrai o
     * payload sintético independente da forma de transporte.
     *
     * @return array<string, mixed>
     */
    private function decodeJsonContent(TestResponse $response): array
    {
        $text = $response->json('result.content.0.text');
        if (is_string($text)) {
            $decoded = json_decode($text, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $direct = $response->json('result');

        return is_array($direct) ? $direct : [];
    }

    public function test_read_capabilities_in_sandbox_work_normally(): void
    {
        AppointmentType::factory()->create(['tenant_id' => $this->tenant->id, 'nome' => 'Consulta Test']);

        SandboxContext::enable(3); // token ID como int

        // Invoca get-clinic-info (read-only)
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
            ['Authorization' => "Bearer {$this->sandboxToken}"]
        );

        $response->assertStatus(200);
        $result = $response->json('result.content.0.text');

        // Dados reais devem estar presentes (read-only funciona normalmente)
        $this->assertStringContainsString('Consulta Test', $result);

        SandboxContext::reset();
    }
}
