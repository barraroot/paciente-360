<?php

declare(strict_types=1);

namespace App\Domain\Ai\Mcp\Capabilities;

use App\Domain\Ai\Tools\GetAvailabilityTool;
use App\Domain\Ai\Tools\Support\ToolInvocationLogger;
use App\Services\Agenda\SlotGeneratorService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request as NativeToolRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * **T046 (Fase 18 — US7, FR-045)** — bridge MCP para {@see GetAvailabilityTool}.
 *
 * Slots reais da Fase 5 (`SlotGeneratorService`) — agenda viva, não lista
 * estática.
 */
#[Name('get-availability')]
#[Description('Consulta os horários realmente disponíveis na agenda. Use quando o paciente perguntar por horários/disponibilidade. NÃO confirma o agendamento.')]
final class GetAvailabilityCapability extends BaseMcpCapability
{
    protected function capabilityName(): string
    {
        return 'get-availability';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'professional_id' => $schema->integer()->description('Opcional — profissional específico.'),
            'appointment_type_id' => $schema->integer()->description('Opcional — tipo de atendimento.'),
            'date_from' => $schema->string()->description('Opcional — data inicial (YYYY-MM-DD).'),
            'date_to' => $schema->string()->description('Opcional — data final (YYYY-MM-DD).'),
        ];
    }

    protected function runReal(Request $request, int $tenantId): string
    {
        $context = $this->resolveToolContext($request, $tenantId);
        $tool = new GetAvailabilityTool(
            $context,
            app(ToolInvocationLogger::class),
            app(SlotGeneratorService::class),
        );

        return $tool->handle(new NativeToolRequest($request->toArray()));
    }
}
