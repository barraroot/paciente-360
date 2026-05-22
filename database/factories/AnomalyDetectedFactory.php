<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\SuperAdmin\Models\AnomalyCategory;
use App\Domain\SuperAdmin\Models\AnomalyDetected;
use App\Domain\SuperAdmin\Models\AnomalySeverity;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * **T131 (Fase 8 — Lote B US-12.3)** — Factory para {@see AnomalyDetected}.
 *
 * @extends Factory<AnomalyDetected>
 */
class AnomalyDetectedFactory extends Factory
{
    protected $model = AnomalyDetected::class;

    public function definition(): array
    {
        return [
            'categoria' => fake()->randomElement(array_column(AnomalyCategory::cases(), 'value')),
            'tenant_id' => Tenant::factory(),
            'severity' => AnomalySeverity::Warning,
            'threshold_breached' => [
                'metric' => 'placeholder',
                'threshold' => 100,
                'observed_value' => 150,
            ],
            'detected_at' => Carbon::now()->subMinutes(fake()->numberBetween(0, 60)),
            'notified_via' => ['inbox'],
            'acknowledged_at' => null,
            'acknowledged_by_user_id' => null,
            'resolved_at' => null,
        ];
    }

    public function critical(): self
    {
        return $this->state(fn (): array => [
            'severity' => AnomalySeverity::Critical,
            'notified_via' => ['inbox', 'email'],
        ]);
    }

    public function categoria(AnomalyCategory $cat): self
    {
        return $this->state(fn (): array => ['categoria' => $cat]);
    }

    public function global(): self
    {
        return $this->state(fn (): array => ['tenant_id' => null]);
    }

    public function acknowledged(): self
    {
        return $this->state(fn (): array => [
            'acknowledged_at' => Carbon::now(),
            'acknowledged_by_user_id' => \App\Models\User::factory(),
        ]);
    }

    public function resolved(): self
    {
        return $this->state(fn (): array => [
            'resolved_at' => Carbon::now(),
        ]);
    }
}
