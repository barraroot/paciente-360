<?php

namespace App\Http\Resources\Ai;

use App\Domain\Ai\Execution\Models\AiExecutionLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiExecutionLog
 *
 * Sem PII clínica: os summaries já são pseudonimizados na escrita do log
 * (AiMessageProcessor + PiiScrubber) — Princípios I/III/V, G11.
 */
final class AiExecutionLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'response_message_id' => $this->response_message_id,
            'channel_type' => $this->channel_type,
            'correlation_id' => $this->correlation_id,
            'classified_intent' => $this->classified_intent,
            'confidence_score' => $this->confidence_score,
            'action' => $this->action,
            'status' => $this->status,
            'latency_ms' => $this->latency_ms,
            'input_tokens' => $this->input_tokens,
            'output_tokens' => $this->output_tokens,
            'estimated_cost' => $this->estimated_cost,
            'persona' => $this->whenLoaded('persona', fn (): ?array => $this->persona === null ? null : [
                'id' => $this->persona->id,
                'name' => $this->persona->name,
            ]),
            // Detalhe (show): conteúdo já pseudonimizado.
            'prompt_summary' => $this->when($request->routeIs('*.show'), $this->prompt_summary),
            'response_summary' => $this->when($request->routeIs('*.show'), $this->response_summary),
            'context_summary' => $this->when($request->routeIs('*.show'), $this->context_summary),
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
