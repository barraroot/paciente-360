<?php

namespace App\Http\Resources\Ai;

use App\Domain\Ai\Persona\Models\AiPersona;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiPersona
 */
final class AiPersonaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'ai_model_id' => $this->ai_model_id,
            'model' => $this->whenLoaded('model', fn (): array => [
                'id' => $this->model->id,
                'name' => $this->model->name,
                'provider' => $this->model->provider,
                'is_active' => $this->model->is_active,
            ]),
            'markdown_content' => $this->markdown_content,
            'tone' => $this->tone,
            'objective' => $this->objective,
            'limitations' => $this->limitations,
            'initial_message' => $this->initial_message,
            'fallback_message' => $this->fallback_message,
            'handoff_rules' => $this->handoff_rules,
            'model_settings' => $this->model_settings ?? [],
            'is_active' => $this->is_active,
            'knowledge_base_ids' => $this->whenLoaded('knowledgeBases', fn (): array => $this->knowledgeBases->pluck('id')->all()),
            'guardrail_ids' => $this->whenLoaded('guardrails', fn (): array => $this->guardrails->pluck('id')->all()),
            'channels_count' => $this->whenCounted('channels'),
            'knowledge_bases_count' => $this->whenCounted('knowledgeBases'),
            'guardrails_count' => $this->whenCounted('guardrails'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
