<?php

declare(strict_types=1);

namespace App\Domain\Ai\Tools\Support;

use App\Domain\Ai\Execution\Models\AiToolInvocation;
use App\Support\Lgpd\PiiScrubber;
use Illuminate\Support\Str;
use Throwable;

/**
 * Feature 017 (US5, FR-031) — grava cada invocação de tool com inputs/resultados
 * MINIMIZADOS e pseudonimizados (sem PII de terceiros nem dado clínico).
 */
final class ToolInvocationLogger
{
    /**
     * @param array<string, mixed> $input
     */
    public function log(ToolContext $context, string $toolName, array $input, string $outcome, ?string $result, int $latencyMs): void
    {
        try {
            AiToolInvocation::create([
                'tenant_id' => $context->tenantId,
                'conversation_id' => $context->conversationId,
                'correlation_id' => $context->correlationId,
                'tool_name' => $toolName,
                'input_summary' => $this->scrubArray($input),
                'outcome' => $outcome,
                'result_summary' => $result !== null
                    ? ['text' => Str::limit((string) PiiScrubber::scrub($result), 500)]
                    : null,
                'latency_ms' => $latencyMs,
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
            $scrubbed[$key] = is_string($value) ? PiiScrubber::scrub($value) : $value;
        }

        return $scrubbed;
    }
}
