<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\Mcp;

use App\Domain\Ai\Mcp\Capabilities\GetClinicInfoCapability;
use App\Domain\Ai\Mcp\Capabilities\ListProfessionalsCapability;
use App\Domain\Ai\Tools\GetClinicInfoTool;
use App\Domain\Ai\Tools\ListProfessionalsTool;
use App\Domain\Ai\Tools\Support\ToolContext;
use App\Domain\Ai\Tools\Support\ToolInvocationLogger;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Agenda\AppointmentType;
use App\Models\Professional;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request as NativeRequest;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Mcp\Server\Tool;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * **T064 — GATE FR-053 (Fase 18 — US7)**: paridade comportamental entre as
 * tools nativas `App\Domain\Ai\Tools\*Tool` (Fase 17) e suas capabilities
 * MCP equivalentes (`App\Domain\Ai\Mcp\Capabilities\*`).
 *
 * **GATE DE CUT-OVER**: enquanto este teste estiver vermelho, NÃO mover o
 * flag `AI_TOOLS_VIA_MCP=true` em produção (FR-053). Cada capability é uma
 * fachada que delega à tool nativa correspondente — paridade deve ser
 * trivial. Quando UMA fachada diverge, este gate quebra.
 *
 * **CI noturno**: tag `parity-gate` separa do CI padrão; rodar com
 * `vendor/bin/sail artisan test --group=parity-gate`.
 *
 * Cobertura: as 6 capabilities da US7. `update-lead-profile` (US3 — T097)
 * NÃO tem tool nativa equivalente, logo não é gate de paridade.
 *
 * Estratégia: para tools de LEITURA (sem efeitos colaterais), comparamos
 * outputs diretamente. Tools de ESCRITA (CreateOrFindLead, HoldSlot) não
 * são testadas aqui — seu comportamento sandbox/real é coberto por
 * SandboxNeutralizationTest (T063) e os testes da Fase 17.
 */
#[Group('parity-gate')]
final class ParityWithNativeToolsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);

        // Conversation real é necessária — `ai_tool_invocations.conversation_id`
        // é NOT NULL (Fase 17) e o ToolInvocationLogger grava 1 linha por call.
        $channel = Channel::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $channel->id,
        ]);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant');
        parent::tearDown();
    }

    public function test_get_clinic_info_capability_matches_native_tool(): void
    {
        AppointmentType::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $nativeOutput = $this->runNative(GetClinicInfoTool::class, ['topic' => 'all']);
        $mcpOutput = $this->runMcp(GetClinicInfoCapability::class, ['topic' => 'all']);

        $this->assertSame($nativeOutput, $mcpOutput, 'GetClinicInfo: capability MCP diverge da tool nativa');
    }

    public function test_list_professionals_capability_matches_native_tool(): void
    {
        Professional::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $nativeOutput = $this->runNative(ListProfessionalsTool::class, []);
        $mcpOutput = $this->runMcp(ListProfessionalsCapability::class, []);

        $this->assertSame($nativeOutput, $mcpOutput, 'ListProfessionals: capability MCP diverge da tool nativa');
    }

    public function test_get_clinic_info_handles_empty_tenant_identically(): void
    {
        $nativeOutput = $this->runNative(GetClinicInfoTool::class, []);
        $mcpOutput = $this->runMcp(GetClinicInfoCapability::class, []);

        $this->assertSame($nativeOutput, $mcpOutput);
        $this->assertStringContainsString('Sem serviços', $nativeOutput);
    }

    public function test_list_professionals_handles_empty_tenant_identically(): void
    {
        $nativeOutput = $this->runNative(ListProfessionalsTool::class, []);
        $mcpOutput = $this->runMcp(ListProfessionalsCapability::class, []);

        $this->assertSame($nativeOutput, $mcpOutput);
        $this->assertStringContainsString('Nenhum profissional', $nativeOutput);
    }

    private function toolContext(): ToolContext
    {
        return new ToolContext(
            tenantId: $this->tenant->id,
            conversationId: $this->conversation->id,
            patientId: null,
            contactPhone: null,
            correlationId: null,
        );
    }

    /**
     * @param array<string, mixed> $args
     */
    private function runNative(string $toolClass, array $args): string
    {
        $tool = new $toolClass($this->toolContext(), app(ToolInvocationLogger::class));

        return $tool->handle(new NativeRequest($args));
    }

    /**
     * Invoca a capability MCP IN-PROCESS (sem HTTP) e extrai o texto.
     *
     * @param array<string, mixed> $args
     */
    private function runMcp(string $capabilityClass, array $args): string
    {
        /** @var Tool $capability */
        $capability = app($capabilityClass);

        $request = new McpRequest($args);
        $request->setMeta([
            'conversation_id' => $this->conversation->id,
            'patient_id' => null,
            'contact_phone' => null,
        ]);

        $response = $capability->handle($request);

        $arr = $response->content()->toArray();
        if (isset($arr['text'])) {
            return (string) $arr['text'];
        }
        if (isset($arr[0]['text'])) {
            return (string) $arr[0]['text'];
        }

        return json_encode($arr, JSON_UNESCAPED_UNICODE) ?: '';
    }
}
