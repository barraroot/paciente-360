<?php

namespace Database\Factories\Agenda;

use App\Models\Agenda\Appointment;
use App\Models\Agenda\AppointmentType;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $startsAt = Carbon::now()->addDays(fake()->numberBetween(1, 30))
            ->setTime(fake()->numberBetween(8, 17), fake()->randomElement([0, 30]), 0);

        $duration = fake()->randomElement([15, 30, 45, 60]);

        return [
            'tenant_id' => Tenant::factory(),
            'idempotency_key' => fake()->uuid(),
            'paciente_id' => Paciente::factory(),
            'professional_id' => Professional::factory(),
            'appointment_type_id' => AppointmentType::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes($duration),
            'status' => 'scheduled',
            'channel_origin' => fake()->randomElement(['painel', 'ia', 'autoatendimento']),
            'created_by_user_id' => null,
            'valor_aplicado' => fake()->randomFloat(2, 50, 500),
            'valor_override_motivo' => null,
            'override_block' => false,
            'override_motivo' => null,
            'notes' => null,
        ];
    }

    public function confirmed(): self
    {
        return $this->state(fn () => [
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    public function canceled(string $quem = 'paciente'): self
    {
        return $this->state(fn () => [
            'status' => 'canceled',
            'quem_cancelou' => $quem,
            'motivo_cancelamento' => 'paciente_via_chat',
            'canceled_at' => now(),
        ]);
    }

    public function realizada(): self
    {
        return $this->state(fn () => [
            'status' => 'realizada',
            'attendance_marked_at' => now(),
        ]);
    }
}
