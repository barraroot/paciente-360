<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory para o Model `Plan` (catálogo de planos comerciais — T021).
 *
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = Str::slug(fake()->unique()->word());

        return [
            'code' => $code,
            'name' => ucfirst($code),
            'description' => fake()->sentence(),
            'base_price_cents' => fake()->numberBetween(4900, 49900),
            'included_professionals' => fake()->numberBetween(1, 10),
            'included_ai_messages' => fake()->numberBetween(500, 5000),
            'overage_price_cents' => 50,
            'max_users' => fake()->numberBetween(5, 50),
            'max_channels' => fake()->numberBetween(1, 5),
            'stripe_price_id_base' => 'price_'.Str::random(14),
            'stripe_price_id_overage' => 'price_'.Str::random(14),
            'is_active' => true,
        ];
    }
}
