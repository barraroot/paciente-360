<?php

namespace Database\Factories\Agenda;

use App\Models\Agenda\AppointmentType;
use App\Models\Agenda\SlotReservation;
use App\Models\Professional;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SlotReservation>
 */
class SlotReservationFactory extends Factory
{
    protected $model = SlotReservation::class;

    public function definition(): array
    {
        $startsAt = Carbon::now()->addDays(fake()->numberBetween(1, 30))
            ->setTime(fake()->numberBetween(8, 17), 0, 0);

        return [
            'tenant_id' => Tenant::factory(),
            'professional_id' => Professional::factory(),
            'appointment_type_id' => AppointmentType::factory(),
            'starts_at' => $startsAt,
            'holder_type' => 'user',
            'holder_id' => (string) fake()->numberBetween(1, 1000),
            'idempotency_key' => fake()->uuid(),
            'acquired_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'released_at' => null,
            'release_reason' => null,
        ];
    }

    public function ia(string $conversationId = 'conv-test'): self
    {
        return $this->state(fn () => [
            'holder_type' => 'ia',
            'holder_id' => $conversationId,
            'expires_at' => now()->addMinutes(2),
        ]);
    }

    public function expired(): self
    {
        return $this->state(fn () => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function committed(): self
    {
        return $this->state(fn () => [
            'released_at' => now(),
            'release_reason' => 'committed',
        ]);
    }
}
