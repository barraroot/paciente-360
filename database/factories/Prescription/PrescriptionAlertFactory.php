<?php

namespace Database\Factories\Prescription;

use App\Domain\Prescription\Alert\AlertStatus;
use App\Domain\Prescription\Alert\AlertType;
use App\Domain\Prescription\Alert\PrescriptionAlert;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PrescriptionAlert>
 */
class PrescriptionAlertFactory extends Factory
{
    protected $model = PrescriptionAlert::class;

    public function definition(): array
    {
        $scheduledFor = Carbon::today()->addDays(15);

        return [
            'prescription_id' => PrescriptionFactory::new(),
            'tenant_id' => Tenant::factory(),
            'alert_type' => AlertType::Days15,
            'status' => AlertStatus::Pending,
            'scheduled_for' => $scheduledFor,
            'dispatched_at' => null,
            'channel_id' => null,
            'message_id' => null,
            'failure_reason' => null,
            'skip_reason' => null,
        ];
    }

    /**
     * Alerta pendente (ainda não disparado).
     */
    public function pending(): self
    {
        return $this->state(fn () => [
            'status' => AlertStatus::Pending,
            'dispatched_at' => null,
        ]);
    }

    /**
     * Alerta já despachado.
     */
    public function dispatched(): self
    {
        return $this->state(fn () => [
            'status' => AlertStatus::Dispatched,
            'dispatched_at' => Carbon::now(),
        ]);
    }

    /**
     * Alerta pulado (checkpoint passado no cadastro).
     */
    public function skipped(string $reason = 'checkpoint_past_at_creation'): self
    {
        return $this->state(fn () => [
            'status' => AlertStatus::Skipped,
            'skip_reason' => $reason,
        ]);
    }
}
