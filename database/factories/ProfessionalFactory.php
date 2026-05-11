<?php

namespace Database\Factories;

use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para o Model `Professional`.
 *
 * @extends Factory<Professional>
 */
class ProfessionalFactory extends Factory
{
    protected $model = Professional::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake('pt_BR')->name(),
            'council_type' => fake()->randomElement(['CRM', 'CRO', 'CREFITO', 'CFP']),
            'council_number' => fake()->numerify('#####'),
            'council_state' => fake()->randomElement(['MG', 'SP', 'RJ', 'RS', 'PR']),
            'is_active' => true,
        ];
    }

    /**
     * Vincula ao tenant especificado.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
