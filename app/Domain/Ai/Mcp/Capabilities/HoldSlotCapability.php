<?php

declare(strict_types=1);

namespace App\Domain\Ai\Mcp\Capabilities;

use App\Domain\Ai\Tools\HoldSlotTool;
use App\Domain\Ai\Tools\Support\ToolInvocationLogger;
use App\Services\Agenda\SlotReservationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request as NativeToolRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * **T049 (Fase 18 — US7, FR-048)** — bridge MCP para {@see HoldSlotTool}.
 *
 * Write reversível (hold tentativo com TTL — `holder_type='ia'`). NÃO confirma
 * agendamento nem solicita pagamento — handoff (FR-023, FR-018 da Fase 17).
 *
 * Em SANDBOX retorna output sintético sem persistir em `slot_reservations`
 * (FR-041, T057 SandboxNeutralizer).
 */
#[Name('hold-slot')]
#[Description('Reserva PROVISORIAMENTE um horário escolhido pelo paciente (a confirmação e o sinal são feitos depois por um atendente). Use após o paciente escolher um horário disponível.')]
final class HoldSlotCapability extends BaseMcpCapability
{
    protected function capabilityName(): string
    {
        return 'hold-slot';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'professional_id' => $schema->integer()->description('Profissional do horário escolhido.')->required(),
            'appointment_type_id' => $schema->integer()->description('Tipo de atendimento.')->required(),
            'starts_at' => $schema->string()->description('Início do horário (ISO 8601).')->required(),
        ];
    }

    protected function runReal(Request $request, int $tenantId): string
    {
        $context = $this->resolveToolContext($request, $tenantId);
        $tool = new HoldSlotTool(
            $context,
            app(ToolInvocationLogger::class),
            app(SlotReservationService::class),
        );

        return $tool->handle(new NativeToolRequest($request->toArray()));
    }

    /**
     * @return array<string, mixed>
     */
    protected function runSandbox(Request $request, int $tenantId): array
    {
        return [
            'slot_reservation_id' => 'sandbox-'.str()->uuid(),
            'sandbox' => true,
            'text' => '[SANDBOX] Hold sintético colocado — nenhuma reserva real em slot_reservations.',
        ];
    }
}
