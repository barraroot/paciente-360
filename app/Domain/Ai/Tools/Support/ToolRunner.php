<?php

declare(strict_types=1);

namespace App\Domain\Ai\Tools\Support;

use App\Domain\Ai\Mcp\CircuitBreaker\McpCircuitBreaker;
use App\Domain\Ai\Mcp\Client\McpToolBridge;
use App\Domain\Ai\Tools\ConversationTool;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Tools\Request as NativeToolRequest;
use Throwable;

/**
 * **T052 (Fase 18 — US7, FR-052/053b)** — roteador flag-aware para invocação
 * de tools da IA.
 *
 * Decisão por chamada (não por boot — permite rollback runtime):
 *
 *   se `AI_TOOLS_VIA_MCP=true` E `McpCircuitBreaker::shouldAllowMcpCall()`:
 *       → invoca via McpToolBridge (HTTP local ao mcp-server)
 *       → em qualquer falha (timeout, 5xx, connection refused):
 *           - CB registra a falha (pode abrir o circuito)
 *           - cai no FALLBACK RUNTIME (tool nativa) — atendimento NÃO para
 *   senão (flag OFF OU CB open):
 *       → invoca a tool nativa diretamente (preservada pelo FR-052)
 *
 * As tools nativas (`App\Domain\Ai\Tools\*Tool`) continuam consumidas
 * pelo `ConversationToolFactory` (Fase 17 — não modificado). Quando o flag
 * MCP está ON, o PersonaAgent vai receber tools wrappers que delegam aqui.
 *
 * No MVP (Setup + Foundational + US1 + US2 + US3), o flag fica OFF — o
 * caminho via MCP só é exercido pelos testes do gate paridade T064 e pelo
 * chat de teste de Persona (US6).
 */
final class ToolRunner
{
    public function __construct(
        private readonly McpToolBridge $bridge,
        private readonly McpCircuitBreaker $circuitBreaker,
    ) {}

    /**
     * Invoca a tool com a melhor estratégia disponível.
     *
     * @param array<string, mixed> $arguments
     */
    public function run(ConversationTool $nativeTool, string $capabilityName, array $arguments, ToolContext $context): string
    {
        if ($this->shouldUseMcp()) {
            try {
                $result = $this->bridge->invoke($capabilityName, $arguments, $context);

                return $this->extractText($result);
            } catch (Throwable $e) {
                // Fallback runtime: CB já registrou a falha; tools nativas
                // assumem para o atendimento NÃO parar.
                Log::warning('mcp.bridge.failed_falling_back_to_native', [
                    'capability' => $capabilityName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $nativeTool->handle(new NativeToolRequest($arguments));
    }

    private function shouldUseMcp(): bool
    {
        if (! (bool) config('ai.matricial.mcp.enabled', false)) {
            return false;
        }

        return $this->circuitBreaker->shouldAllowMcpCall();
    }

    /**
     * @param array<string, mixed> $mcpResponse
     */
    private function extractText(array $mcpResponse): string
    {
        // Formato MCP padrão: result.content[0].text OU result em forma livre.
        if (isset($mcpResponse['result']['content'][0]['text'])) {
            return (string) $mcpResponse['result']['content'][0]['text'];
        }
        if (isset($mcpResponse['result']) && is_string($mcpResponse['result'])) {
            return $mcpResponse['result'];
        }
        if (isset($mcpResponse['text']) && is_string($mcpResponse['text'])) {
            return $mcpResponse['text'];
        }

        // Estrutura JSON livre — devolve serializada como fallback.
        return json_encode($mcpResponse['result'] ?? $mcpResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}
