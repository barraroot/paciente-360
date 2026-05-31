<?php

declare(strict_types=1);

namespace App\Domain\Ai\Mcp\Server;

use App\Domain\Ai\Mcp\Capabilities\CreateOrFindLeadCapability;
use App\Domain\Ai\Mcp\Capabilities\GetAvailabilityCapability;
use App\Domain\Ai\Mcp\Capabilities\GetClinicInfoCapability;
use App\Domain\Ai\Mcp\Capabilities\GetCurrentPatientCapability;
use App\Domain\Ai\Mcp\Capabilities\HoldSlotCapability;
use App\Domain\Ai\Mcp\Capabilities\ListProfessionalsCapability;
use App\Domain\Ai\Mcp\Capabilities\UpdateLeadProfileCapability;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * **T043 (Fase 18 — US7, FR-045)** — servidor MCP da plataforma.
 *
 * Expõe as capabilities equivalentes às 6 tools nativas da Fase 17
 * (`App\Domain\Ai\Tools\*`) — preservadas como FALLBACK RUNTIME (FR-052)
 * sob circuit breaker.
 *
 * Sob `AI_TOOLS_VIA_MCP=true` (Q2=B — substituição) o `McpToolBridge` (T051)
 * roteia toda chamada de tool da IA para este servidor via HTTP local.
 * Quando `false`, o `ToolRunner` (T052) usa as tools nativas diretamente.
 *
 * `UpdateLeadProfileCapability` (US3 — T097) será acrescido posteriormente.
 */
#[Name('Paciente360 MCP Server')]
#[Version('1.0.0')]
#[Instructions('Servidor MCP da plataforma Paciente360. Expõe operações de leitura de clínica/profissionais/agenda + ações reversíveis (criar/buscar lead, hold tentativo de slot) escopadas ao tenant da credencial. NÃO confirma agendamento nem cobrança — esses fluxos são tratados pelo backend principal.')]
final class PacienteMcpServer extends Server
{
    /** @var array<int, class-string> */
    protected array $tools = [
        GetClinicInfoCapability::class,
        ListProfessionalsCapability::class,
        GetAvailabilityCapability::class,
        GetCurrentPatientCapability::class,
        CreateOrFindLeadCapability::class,
        HoldSlotCapability::class,
        UpdateLeadProfileCapability::class, // T098 — US3.
    ];

    /** @var array<int, class-string> */
    protected array $resources = [];

    /** @var array<int, class-string> */
    protected array $prompts = [];
}
