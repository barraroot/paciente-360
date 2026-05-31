<?php

declare(strict_types=1);

namespace App\Domain\Ai\Mcp\Capabilities;

use App\Domain\Ai\Mcp\Client\McpCallLogger;
use App\Domain\Ai\Mcp\Client\McpToolBridge;
use App\Domain\Ai\Mcp\Sandbox\SandboxContext;
use App\Domain\Ai\Tools\Support\ToolContext;
use App\Support\Metrics\AiMetricsContract;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Throwable;

/**
 * **(Fase 18 — US7)** — base das capabilities MCP. Centraliza auditoria
 * (FR-049, T050), degradação graciosa (FR-053b → cliente decide fallback)
 * e propagação de sandbox (FR-040/041).
 *
 * Subclasses implementam `capabilityName()` + `runReal()` + `runSandbox()`
 * (este último com no-op/output sintético para capabilities de escrita;
 * leituras delegam ao mesmo `runReal()`).
 *
 * `tenant_id` SEMPRE vem do contexto da credencial (resolvido pelo
 * McpTokenGuard, T041) — NUNCA do input do cliente (FR-046/050).
 */
abstract class BaseMcpCapability extends Tool
{
    public function __construct(
        protected readonly McpCallLogger $logger,
        protected readonly AiMetricsContract $metrics,
    ) {}

    abstract protected function capabilityName(): string;

    /**
     * Caminho real (com efeitos colaterais). Retorna estrutura de output
     * que será serializada pelo {@see Response}.
     *
     * @return array<string, mixed>|string
     */
    abstract protected function runReal(Request $request, int $tenantId): array|string;

    /**
     * Caminho sandbox (FR-040/041) — capabilities de escrita override este
     * método retornando output sintético sem persistência. Default delega
     * ao caminho real (capabilities de LEITURA são fiéis no sandbox).
     *
     * @return array<string, mixed>|string
     */
    protected function runSandbox(Request $request, int $tenantId): array|string
    {
        return $this->runReal($request, $tenantId);
    }

    public function handle(Request $request): Response
    {
        $tenantId = (int) (app()->bound('tenant') ? app('tenant')->id : 0);
        if ($tenantId === 0) {
            return Response::error('tenant_required');
        }

        $sandbox = SandboxContext::enabled();
        $startedAt = microtime(true);
        $outcome = 'success';
        $output = null;

        try {
            $output = $sandbox
                ? $this->runSandbox($request, $tenantId)
                : $this->runReal($request, $tenantId);
        } catch (Throwable $e) {
            $outcome = 'error';
            $output = ['error' => 'capability_failed', 'message' => $e->getMessage()];
        }

        $durationS = microtime(true) - $startedAt;

        $this->logger->log(
            tenantId: $tenantId,
            capability: $this->capabilityName(),
            input: $this->safeInput($request),
            outcome: $outcome,
            result: is_array($output) ? $output : ['text' => $output],
            durationMs: (int) round($durationS * 1000),
            sandbox: $sandbox,
        );

        $this->metrics->mcpRequestDuration(
            capability: $this->capabilityName(),
            outcome: $outcome,
            source: $sandbox ? 'sandbox' : 'production',
            seconds: $durationS,
        );

        if (is_string($output)) {
            return Response::text($output);
        }

        return Response::json($output);
    }

    /**
     * Resolve o {@see ToolContext} (compartilhado com a Fase 17) a partir da
     * request MCP. O `meta` do MCP carrega `conversation_id`, `patient_id`,
     * `contact_phone` quando o {@see McpToolBridge}
     * invoca em nome de um turno da IA; para chamadas externas (Claude Desktop)
     * apenas o `tenantId` é populado.
     */
    protected function resolveToolContext(Request $request, int $tenantId): ToolContext
    {
        $meta = $request->meta() ?? [];

        return new ToolContext(
            tenantId: $tenantId,
            conversationId: (int) ($meta['conversation_id'] ?? 0),
            patientId: isset($meta['patient_id']) ? (int) $meta['patient_id'] : null,
            contactPhone: $meta['contact_phone'] ?? null,
            correlationId: $meta['correlation_id'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function safeInput(Request $request): array
    {
        // Remove campos com PII potencial (capabilities NÃO devem aceitar PII
        // de input — schemas declaram só os campos legítimos, mas é defesa
        // em profundidade contra schema-bypass).
        $input = $request->toArray();
        foreach (['cpf', 'rg', 'email', 'phone', 'name', 'full_name'] as $field) {
            if (array_key_exists($field, $input)) {
                $input[$field] = '<redacted>';
            }
        }

        return $input;
    }
}
