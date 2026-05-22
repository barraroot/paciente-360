<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Reports\Models\MetricAggregation;
use App\Domain\Reports\Models\MetricPeriod;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<MetricAggregation>
 */
class MetricAggregationFactory extends Factory
{
    protected $model = MetricAggregation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'metric_name' => 'leads_by_channel',
            'period' => MetricPeriod::Day,
            'period_start' => Carbon::today(),
            'dimensions' => null,
            'value_numeric' => fake()->randomFloat(2, 0, 100),
            'value_json' => null,
            'computed_at' => Carbon::now(),
        ];
    }

    public function metric(string $name): self
    {
        return $this->state(fn (): array => ['metric_name' => $name]);
    }

    public function period(MetricPeriod $period): self
    {
        return $this->state(fn (): array => ['period' => $period]);
    }
}
