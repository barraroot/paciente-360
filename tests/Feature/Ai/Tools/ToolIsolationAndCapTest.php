<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\Tools;

use App\Domain\Ai\Agents\PersonaAgent;
use App\Domain\Ai\Tools\ConversationTool;
use App\Domain\Ai\Tools\GetClinicInfoTool;
use App\Domain\Ai\Tools\GetCurrentPatientTool;
use App\Domain\Ai\Tools\Support\ToolContext;
use App\Domain\Ai\Tools\Support\ToolInvocationLogger;
use App\Models\Agenda\AppointmentType;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Tools\Request;
use ReflectionClass;
use RuntimeException;
use Tests\Support\AiConversationFactory;
use Tests\TestCase;

/**
 * Feature 017 (US5) — T045/T046: isolamento de tenant/paciente no data layer
 * (FR-034/SC-007), cap de round-trips (FR-032) e degradação em falha (FR-033).
 */
final class ToolIsolationAndCapTest extends TestCase
{
    use RefreshDatabase;

    public function test_tool_never_returns_another_tenants_data(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        AppointmentType::factory()->create([
            'tenant_id' => $tenantA->id,
            'nome' => 'ServicoSecretoDeA',
            'is_active' => true,
        ]);

        // Operando como tenant B.
        $this->app->instance('tenant', $tenantB);
        $conversationB = AiConversationFactory::conversation($tenantB);
        $ctxB = new ToolContext($tenantB->id, $conversationB->id, null, null, 'corr-b');

        $result = (new GetClinicInfoTool($ctxB, app(ToolInvocationLogger::class)))->handle(new Request(['topic' => 'all']));

        $this->assertStringNotContainsString('ServicoSecretoDeA', $result);
    }

    public function test_current_patient_tool_does_not_match_other_patients(): void
    {
        $tenant = Tenant::factory()->create();
        $this->app->instance('tenant', $tenant);

        // Paciente de outro contato (telefone diferente).
        Paciente::factory()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Outro Paciente',
            'telefone_primario' => '+5511911112222',
            'status' => 'ativo',
        ]);

        $conversation = AiConversationFactory::conversation($tenant);
        $ctx = new ToolContext($tenant->id, $conversation->id, null, '+5579999990000', 'corr-x');

        $result = (new GetCurrentPatientTool($ctx, app(ToolInvocationLogger::class)))->handle(new Request([]));

        $this->assertStringContainsString('não cadastrado', $result);
        $this->assertStringNotContainsString('Outro Paciente', $result);
    }

    public function test_persona_agent_caps_tool_round_trips_at_three(): void
    {
        $attributes = (new ReflectionClass(PersonaAgent::class))->getAttributes(MaxSteps::class);

        $this->assertNotEmpty($attributes);
        $this->assertSame(3, $attributes[0]->newInstance()->value);
    }

    public function test_tool_degrades_gracefully_on_failure(): void
    {
        $tenant = Tenant::factory()->create();
        $this->app->instance('tenant', $tenant);
        $conversation = AiConversationFactory::conversation($tenant);
        $ctx = new ToolContext($tenant->id, $conversation->id, null, null, 'corr-f');

        $tool = new class($ctx, app(ToolInvocationLogger::class)) extends ConversationTool
        {
            public function description(): string
            {
                return 'tool de teste que falha';
            }

            public function schema(JsonSchema $schema): array
            {
                return [];
            }

            protected function toolName(): string
            {
                return 'failing-tool';
            }

            protected function run(Request $request): string
            {
                throw new RuntimeException('boom');
            }
        };

        $result = $tool->handle(new Request([]));

        // Não inventa: mensagem neutra + auditoria de erro.
        $this->assertStringContainsString('atendente', $result);
        $this->assertDatabaseHas('ai_tool_invocations', [
            'tenant_id' => $tenant->id,
            'tool_name' => 'failing-tool',
            'outcome' => 'error',
        ]);
    }
}
