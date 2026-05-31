<?php

declare(strict_types=1);

namespace App\Domain\Ai\Mcp\Client;

use App\Domain\Ai\Execution\Models\AiToolInvocation;
use App\Support\Lgpd\PiiScrubber;
use Illuminate\Support\Str;
use Throwable;

/**
 * **T050 (Fase 18 — US7, FR-049)** — grava cada invocação de capability MCP
 * em `ai_tool_invocations` com `source='mcp'`.
 *
 * Reusa a mesma tabela das tools nativas (Fase 17) — diferenciado apenas
 * pela coluna `source` (T050a migration). Garante equivalência de auditoria
 * entre caminhos (FR-049: "mesmas regras de retenção da Fase 17").
 *
 * Não recebe ToolContext (diferente do `ToolInvocationLogger` da Fase 17):
 * o tenant_id vem do McpTokenGuard e conversation_id é opcional (capability
 * pode ser invocada por integração externa sem conversa associada).
 */
final class McpCallLogger
{
    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $result
     */
    public function log(
        int $tenantId,
        string $capability,
        array $input,
        string $outcome,
        array $result,
        int $durationMs,
        bool $sandbox = false,
        ?int $conversationId = null,
        ?string $correlationId = null,
    ): void {
        try {
            AiToolInvocation::create([
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'correlation_id' => $correlationId,
                'tool_name' => $capability,
                'source' => 'mcp',
                'sandbox' => $sandbox,
                'input_summary' => $this->scrubArray($input),
                'outcome' => $outcome,
                'result_summary' => $this->scrubResult($result),
                'latency_ms' => $durationMs,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Auditoria nunca quebra a resposta.
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function scrubArray(array $input): array
    {
        $scrubbed = [];
        foreach ($input as $key => $value) {
            $scrubbed[$key] = is_string($value)
                ? PiiScrubber::scrub($value)
                : $value;
        }

        return $scrubbed;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function scrubResult(array $result): array
    {
        $scrubbed = [];
        foreach ($result as $key => $value) {
            if (is_string($value)) {
                $scrubbed[$key] = Str::limit((string) PiiScrubber::scrub($value), 500);

                continue;
            }
            if (is_array($value)) {
                $scrubbed[$key] = $this->scrubResult($value);

                continue;
            }
            $scrubbed[$key] = $value;
        }

        return $scrubbed;
    }
}
