<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\SuperAdmin\Models\PlanVersion;
use App\Domain\SuperAdmin\Models\TenantPlanBinding;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * **T119 (Fase 8 — Lote B US-12.2)** — Factory para {@see TenantPlanBinding}.
 *
 * @extends Factory<TenantPlanBinding>
 */
class TenantPlanBindingFactory extends Factory
{
    protected $model = TenantPlanBinding::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plan_version_id' => PlanVersion::factory(),
            'effective_from' => Carbon::now(),
            'effective_to' => null,
            'changed_by_user_id' => null,
            'change_reason' => null,
        ];
    }

    public function active(): self
    {
        return $this->state(fn (): array => ['effective_to' => null]);
    }

    public function superseded(?Carbon $at = null): self
    {
        return $this->state(fn (): array => ['effective_to' => $at ?? Carbon::now()]);
    }
}
