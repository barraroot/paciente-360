<?php

declare(strict_types=1);

namespace App\Domain\Ai\Mcp\Capabilities;

use App\Domain\Ai\Tools\CreateOrFindLeadTool;
use App\Domain\Ai\Tools\Support\ToolInvocationLogger;
use App\Services\Pacientes\PacienteService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request as NativeToolRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * **T048 (Fase 18 — US7, FR-048)** — bridge MCP para {@see CreateOrFindLeadTool}.
 *
 * Write reversível (cria/busca lead). Em SANDBOX retorna output sintético
 * sem persistir em `pacientes` (FR-041, T057 SandboxNeutralizer).
 */
#[Name('create-or-find-lead')]
#[Description('Garante que o contato atual exista como lead no sistema (cria se necessário). Use quando avançar o atendimento de um novo contato.')]
final class CreateOrFindLeadCapability extends BaseMcpCapability
{
    protected function capabilityName(): string
    {
        return 'create-or-find-lead';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function runReal(Request $request, int $tenantId): string
    {
        $context = $this->resolveToolContext($request, $tenantId);
        $tool = new CreateOrFindLeadTool(
            $context,
            app(ToolInvocationLogger::class),
            app(PacienteService::class),
        );

        return $tool->handle(new NativeToolRequest($request->toArray()));
    }

    /**
     * @return array<string, mixed>
     */
    protected function runSandbox(Request $request, int $tenantId): array
    {
        return [
            'patient_id' => 'sandbox-'.str()->uuid(),
            'created' => true,
            'status' => 'lead',
            'sandbox' => true,
            'text' => '[SANDBOX] Lead sintético criado — nenhuma linha persistida em pacientes.',
        ];
    }
}
