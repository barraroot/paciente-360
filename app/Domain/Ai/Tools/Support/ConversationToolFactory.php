<?php

declare(strict_types=1);

namespace App\Domain\Ai\Tools\Support;

use App\Domain\Ai\Tools\CreateOrFindLeadTool;
use App\Domain\Ai\Tools\GetAvailabilityTool;
use App\Domain\Ai\Tools\GetClinicInfoTool;
use App\Domain\Ai\Tools\GetCurrentPatientTool;
use App\Domain\Ai\Tools\HoldSlotTool;
use App\Domain\Ai\Tools\ListProfessionalsTool;
use App\Services\Agenda\SlotGeneratorService;
use App\Services\Agenda\SlotReservationService;
use App\Services\Pacientes\PacienteService;
use Laravel\Ai\Contracts\Tool;

/**
 * Feature 017 (US5) — monta as ferramentas de dados ao vivo escopadas a uma
 * conversa. Vazio quando `ai.matricial.tools.enabled` está desligado.
 */
final class ConversationToolFactory
{
    public function __construct(
        private readonly ToolInvocationLogger $logger,
        private readonly SlotGeneratorService $slots,
        private readonly SlotReservationService $reservations,
        private readonly PacienteService $patients,
    ) {}

    /**
     * @return list<Tool>
     */
    public function make(ToolContext $context): array
    {
        if (! (bool) config('ai.matricial.tools.enabled', true)) {
            return [];
        }

        return [
            new GetClinicInfoTool($context, $this->logger),
            new ListProfessionalsTool($context, $this->logger),
            new GetAvailabilityTool($context, $this->logger, $this->slots),
            new GetCurrentPatientTool($context, $this->logger),
            new CreateOrFindLeadTool($context, $this->logger, $this->patients),
            new HoldSlotTool($context, $this->logger, $this->reservations),
        ];
    }
}
