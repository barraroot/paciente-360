<?php

namespace App\Http\Resources\Ai;

use App\Domain\Ai\KnowledgeBase\Models\AiKnowledgeBase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiKnowledgeBase
 */
final class AiKnowledgeBaseResource extends JsonResource
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
            'markdown_content' => $this->markdown_content,
            'tags' => $this->tags ?? [],
            'metadata' => $this->metadata ?? [],
            'is_active' => $this->is_active,
            'indexed_at' => $this->indexed_at?->toIso8601String(),
            'chunks_count' => $this->whenCounted('chunks'),
            'personas_count' => $this->whenCounted('personas'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
