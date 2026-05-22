<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignChannel;
use App\Domain\Campaigns\Models\CampaignStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * **T157 (Fase 8 — Lote C US-9.1)** — Factory para {@see Campaign}.
 *
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        $tenant = Tenant::factory()->create();

        return [
            'tenant_id' => $tenant->id,
            'name' => fake()->randomElement(['Reativação Maio', 'Vacinação 2026', 'Check-up Anual']),
            'status' => CampaignStatus::Draft,
            'channel' => CampaignChannel::WhatsApp,
            'template_id' => null,
            'audience_filters' => [
                'inactivity_months' => 6,
                'tags' => [],
            ],
            'scheduled_for' => null,
            'dispatched_at' => null,
            'total_eligible' => null,
            'total_dispatched' => 0,
            'total_blocked' => 0,
            'daily_limit_applied' => 200,
            'canceled_at' => null,
            'canceled_by_user_id' => null,
            'canceled_reason' => null,
            'created_by_user_id' => User::factory()->state(['tenant_id' => $tenant->id]),
        ];
    }

    public function draft(): self
    {
        return $this->state(fn (): array => ['status' => CampaignStatus::Draft]);
    }

    public function scheduled(?Carbon $for = null): self
    {
        return $this->state(fn (): array => [
            'status' => CampaignStatus::Scheduled,
            'scheduled_for' => $for ?? Carbon::now()->addDays(7),
        ]);
    }

    public function dispatching(): self
    {
        return $this->state(fn (): array => [
            'status' => CampaignStatus::Dispatching,
            'dispatched_at' => Carbon::now(),
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn (): array => [
            'status' => CampaignStatus::Completed,
            'dispatched_at' => Carbon::now()->subHour(),
        ]);
    }

    public function canceled(?string $reason = null): self
    {
        return $this->state(fn (): array => [
            'status' => CampaignStatus::Canceled,
            'canceled_at' => Carbon::now(),
            'canceled_reason' => $reason ?? 'Test cancellation',
        ]);
    }
}
