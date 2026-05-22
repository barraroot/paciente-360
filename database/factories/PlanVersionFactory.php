<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\SuperAdmin\Models\PlanVersion;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * **T119 (Fase 8 — Lote B US-12.2)** — Factory para {@see PlanVersion}.
 *
 * @extends Factory<PlanVersion>
 */
class PlanVersionFactory extends Factory
{
    protected $model = PlanVersion::class;

    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'version' => 1,
            'valid_from' => Carbon::now(),
            'valid_to' => null,
            'snapshot' => [
                'name' => fake()->randomElement(['Básico', 'Pro', 'Enterprise']),
                'base_price_cents' => fake()->randomElement([9900, 29900, 99900]),
                'included_professionals' => 3,
                'included_ai_messages' => 1000,
                'daily_campaign_limit' => 200,
                'api_rate_limit_per_minute' => 100,
                'webhook_max_endpoints' => 5,
                'features_enabled' => [],
            ],
            'created_by_user_id' => null,
        ];
    }

    public function active(): self
    {
        return $this->state(fn (): array => ['valid_to' => null]);
    }

    public function superseded(?Carbon $at = null): self
    {
        return $this->state(fn (): array => ['valid_to' => $at ?? Carbon::now()]);
    }

    public function forVersion(int $version): self
    {
        return $this->state(fn (): array => ['version' => $version]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function withSnapshotOverride(array $overrides): self
    {
        return $this->state(fn (array $attrs): array => [
            'snapshot' => array_merge($attrs['snapshot'] ?? [], $overrides),
        ]);
    }

    public function proTier(): self
    {
        return $this->withSnapshotOverride([
            'name' => 'Pro',
            'daily_campaign_limit' => 1000,
            'api_rate_limit_per_minute' => 1000,
            'webhook_max_endpoints' => 20,
        ]);
    }

    public function enterpriseTier(): self
    {
        return $this->withSnapshotOverride([
            'name' => 'Enterprise',
            'daily_campaign_limit' => 5000,
            'api_rate_limit_per_minute' => 5000,
            'webhook_max_endpoints' => 100,
        ]);
    }
}
