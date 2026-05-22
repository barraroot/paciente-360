<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\CampaignRecipientStatus;
use App\Models\Paciente;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CampaignRecipient>
 */
class CampaignRecipientFactory extends Factory
{
    protected $model = CampaignRecipient::class;

    public function definition(): array
    {
        $campaign = Campaign::factory()->create();

        return [
            'tenant_id' => $campaign->tenant_id,
            'campaign_id' => $campaign->id,
            'patient_id' => Paciente::factory()->state(['tenant_id' => $campaign->tenant_id]),
            'dispatched_at' => null,
            'status' => CampaignRecipientStatus::Pending,
            'blocked_reason' => null,
            'external_message_id' => null,
            'delivered_at' => null,
            'read_at' => null,
            'responded_at' => null,
            'attributed_appointment_id' => null,
        ];
    }

    public function sent(): self
    {
        return $this->state(fn (): array => [
            'status' => CampaignRecipientStatus::Sent,
            'dispatched_at' => Carbon::now(),
            'external_message_id' => 'msg_'.fake()->uuid(),
        ]);
    }

    public function blocked(string $reason): self
    {
        return $this->state(fn (): array => [
            'status' => CampaignRecipientStatus::Blocked,
            'blocked_reason' => $reason,
            'dispatched_at' => Carbon::now(),
        ]);
    }
}
