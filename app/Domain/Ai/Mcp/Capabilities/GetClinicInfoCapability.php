<?php

declare(strict_types=1);

namespace App\Domain\Ai\Mcp\Capabilities;

use App\Domain\Ai\Tools\GetClinicInfoTool;
use App\Domain\Ai\Tools\Support\ToolInvocationLogger;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request as NativeToolRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * **T044 (Fase 18 — US7, FR-045)** — bridge MCP para a tool nativa
 * {@see GetClinicInfoTool} da Fase 17.
 *
 * Delega ao código de produção da Fase 17 (zero drift) — paridade
 * comportamental garantida pelo gate T064.
 */
#[Name('get-clinic-info')]
#[Description('Consulta os serviços/procedimentos oferecidos pela clínica e seus valores atuais. Use quando o paciente perguntar o que a clínica faz ou quanto custa.')]
final class GetClinicInfoCapability extends BaseMcpCapability
{
    protected function capabilityName(): string
    {
        return 'get-clinic-info';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'topic' => $schema->string()
                ->description('Opcional: services | pricing | all')
                ->enum(['services', 'pricing', 'all']),
        ];
    }

    protected function runReal(Request $request, int $tenantId): string
    {
        $context = $this->resolveToolContext($request, $tenantId);
        $tool = new GetClinicInfoTool($context, app(ToolInvocationLogger::class));

        return $tool->handle(new NativeToolRequest($request->toArray()));
    }
}
