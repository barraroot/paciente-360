<?php

declare(strict_types=1);

namespace App\Domain\Ai\Mcp\Capabilities;

use App\Domain\Ai\Tools\GetCurrentPatientTool;
use App\Domain\Ai\Tools\Support\ToolInvocationLogger;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request as NativeToolRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * **T047 (Fase 18 — US7, FR-029/047)** — bridge MCP para {@see GetCurrentPatientTool}.
 *
 * NUNCA retorna nome do paciente (FR-029). Resolve APENAS o contato vinculado
 * ao contexto da credencial — nada de busca por nome aberta.
 */
#[Name('get-current-patient')]
#[Description('Retorna o contexto do contato ATUAL da conversa (se já é conhecido/lead/paciente). Não retorna nome nem dados de outras pessoas.')]
final class GetCurrentPatientCapability extends BaseMcpCapability
{
    protected function capabilityName(): string
    {
        return 'get-current-patient';
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
        $tool = new GetCurrentPatientTool($context, app(ToolInvocationLogger::class));

        return $tool->handle(new NativeToolRequest($request->toArray()));
    }
}
