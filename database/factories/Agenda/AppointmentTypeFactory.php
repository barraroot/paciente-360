<?php

namespace Database\Factories\Agenda;

use App\Models\Agenda\AppointmentType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AppointmentType>
 */
class AppointmentTypeFactory extends Factory
{
    protected $model = AppointmentType::class;

    public function definition(): array
    {
        $nome = fake()->randomElement(['Consulta', 'Retorno', 'Exame', 'Procedimento']);

        return [
            'tenant_id' => Tenant::factory(),
            'nome' => $nome,
            'slug' => Str::slug($nome).'-'.Str::random(4),
            'duration_minutes' => fake()->randomElement([15, 30, 45, 60]),
            'buffer_minutes' => fake()->randomElement([0, 5, 10]),
            'valor_particular' => fake()->randomFloat(2, 50, 500),
            'valor_convenio_default' => fake()->boolean(70) ? fake()->randomFloat(2, 30, 300) : null,
            'min_cancellation_hours' => fake()->boolean(40) ? fake()->randomElement([2, 4, 12, 24, 48]) : null,
            'cor' => fake()->hexColor(), // já inclui '#' (formato '#aaaaaa', 7 chars)
            'descricao' => fake()->boolean(50) ? fake()->sentence() : null,
            'intent_ia' => fake()->boolean(30) ? fake()->word() : null,
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
