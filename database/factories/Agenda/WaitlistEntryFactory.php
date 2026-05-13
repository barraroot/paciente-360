<?php

namespace Database\Factories\Agenda;

use App\Models\Agenda\AppointmentType;
use App\Models\Agenda\WaitlistEntry;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
{
    protected $model = WaitlistEntry::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'paciente_id' => Paciente::factory(),
            'professional_id' => Professional::factory(),
            'appointment_type_id' => AppointmentType::factory(),
            'status' => 'waiting',
            'position' => fake()->numberBetween(1, 50),
            'notified_at' => null,
            'notified_for_slot_starts_at' => null,
            'expires_at' => null,
            'accepted_appointment_id' => null,
        ];
    }

    public function notified(): self
    {
        return $this->state(fn () => [
            'status' => 'notified',
            'notified_at' => now(),
            'notified_for_slot_starts_at' => now()->addDay(),
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    public function expired(): self
    {
        return $this->state(fn () => [
            'status' => 'expired',
            'notified_at' => now()->subMinutes(20),
            'expires_at' => now()->subMinutes(5),
        ]);
    }
}
