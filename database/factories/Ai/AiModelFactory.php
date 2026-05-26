<?php

namespace Database\Factories\Ai;

use App\Domain\Ai\Model\Models\AiModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiModel>
 */
class AiModelFactory extends Factory
{
    protected $model = AiModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Claude Haiku 4.5',
            'provider' => 'anthropic',
            'internal_identifier' => 'claude-haiku-4-5-'.fake()->unique()->numerify('########'),
            'description' => fake()->sentence(),
            'config_schema' => [
                'temperature' => ['min' => 0, 'max' => 1],
                'max_tokens' => ['max' => 4096],
            ],
            'supports_embedding' => false,
            'embedding_dimension' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function embedding(): static
    {
        return $this->state([
            'name' => 'Text Embedding 3 Small',
            'provider' => 'openai',
            'internal_identifier' => 'text-embedding-3-small-'.fake()->unique()->numerify('######'),
            'supports_embedding' => true,
            'embedding_dimension' => 1536,
        ]);
    }
}
