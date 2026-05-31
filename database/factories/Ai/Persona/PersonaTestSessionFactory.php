<?php

namespace Database\Factories\Ai\Persona;

use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\Persona\Models\PersonaTestSession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonaTestSession>
 */
class PersonaTestSessionFactory extends Factory
{
    protected $model = PersonaTestSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'persona_id' => AiPersona::factory(),
            'persona_snapshot' => [
                'id' => fake()->randomNumber(),
                'name' => 'Test Persona',
                'markdown_content' => '# Test',
                'tone' => 'cordial',
                'objective' => 'Test',
                'limitations' => 'Test',
                'initial_message' => 'Olá!',
                'fallback_message' => 'Huh?',
                'handoff_rules' => 'Encaminhe',
                'voice_id' => null,
                'ai_model_id' => 1,
            ],
            'admin_user_id' => User::factory(),
            'mcp_token_id' => null,
            'status' => 'open',
            'closed_at' => null,
            'archived_at' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state([
            'status' => 'closed',
            'closed_at' => now(),
            'mcp_token_id' => null,
        ]);
    }

    public function archived(): static
    {
        return $this->state([
            'status' => 'archived',
            'archived_at' => now(),
        ]);
    }
}
