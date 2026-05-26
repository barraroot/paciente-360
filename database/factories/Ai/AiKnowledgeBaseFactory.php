<?php

namespace Database\Factories\Ai;

use App\Domain\Ai\KnowledgeBase\Models\AiKnowledgeBase;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiKnowledgeBase>
 */
class AiKnowledgeBaseFactory extends Factory
{
    protected $model = AiKnowledgeBase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'markdown_content' => "# Base de Conhecimento\n\n## Horários\n\nSeg a Sex, 8h às 18h.",
            'tags' => ['faq', 'horarios'],
            'metadata' => [],
            'is_active' => true,
            'indexed_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
