<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignDispatchLog;
use App\Models\Paciente;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CampaignDispatchLog>
 */
class CampaignDispatchLogFactory extends Factory
{
    protected $model = CampaignDispatchLog::class;

    public function definition(): array
    {
        $campaign = Campaign::factory()->create();

        return [
            'tenant_id' => $campaign->tenant_id,
            'campaign_id' => $campaign->id,
            'patient_id' => Paciente::factory()->state(['tenant_id' => $campaign->tenant_id]),
            'attempted_at' => Carbon::now()->subMinutes(fake()->numberBetween(0, 60)),
            'result' => 'sent',
            'block_reason' => null,
            'details' => null,
        ];
    }

    public function blocked(string $reason): self
    {
        return $this->state(fn (): array => [
            'result' => 'blocked',
            'block_reason' => $reason,
        ]);
    }
}
