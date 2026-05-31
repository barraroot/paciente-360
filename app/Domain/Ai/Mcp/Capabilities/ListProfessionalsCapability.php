<?php

declare(strict_types=1);

namespace App\Domain\Ai\Mcp\Capabilities;

use App\Domain\Ai\Tools\ListProfessionalsTool;
use App\Domain\Ai\Tools\Support\ToolInvocationLogger;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request as NativeToolRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * **T045 (Fase 18 — US7, FR-045)** — bridge MCP para {@see ListProfessionalsTool}.
 */
#[Name('list-professionals')]
#[Description('Lista os profissionais que atendem na clínica (nome e especialidade). Use quando o paciente perguntar quem atende.')]
final class ListProfessionalsCapability extends BaseMcpCapability
{
    protected function capabilityName(): string
    {
        return 'list-professionals';
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
        $tool = new ListProfessionalsTool($context, app(ToolInvocationLogger::class));

        return $tool->handle(new NativeToolRequest($request->toArray()));
    }
}
