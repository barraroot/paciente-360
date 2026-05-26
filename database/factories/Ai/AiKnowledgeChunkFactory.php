<?php

namespace Database\Factories\Ai;

use App\Domain\Ai\KnowledgeBase\Models\AiKnowledgeBase;
use App\Domain\Ai\KnowledgeBase\Models\AiKnowledgeChunk;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiKnowledgeChunk>
 */
class AiKnowledgeChunkFactory extends Factory
{
    protected $model = AiKnowledgeChunk::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'ai_knowledge_base_id' => AiKnowledgeBase::factory(),
            'chunk_index' => 0,
            'content' => fake()->paragraph(),
            'token_count' => fake()->numberBetween(20, 200),
        ];
    }

    public function forBase(AiKnowledgeBase $base): static
    {
        return $this->state([
            'ai_knowledge_base_id' => $base->id,
            'tenant_id' => $base->tenant_id,
        ]);
    }
}
