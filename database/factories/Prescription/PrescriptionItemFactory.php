<?php

namespace Database\Factories\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\PrescriptionItem\PrescriptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrescriptionItem>
 */
class PrescriptionItemFactory extends Factory
{
    protected $model = PrescriptionItem::class;

    public function definition(): array
    {
        return [
            'prescription_id' => PrescriptionFactory::new(),
            'medication_name' => fake()->randomElement([
                'Losartana 50mg',
                'Metformina 500mg',
                'Sinvastatina 20mg',
                'Omeprazol 20mg',
                'Paracetamol 750mg',
                'Atorvastatina 40mg',
                'Amlodipina 5mg',
                'Hidroclorotiazida 25mg',
            ]),
            'concentration' => fake()->optional(0.5)->randomElement(['25mg', '50mg', '100mg', '250mg', '500mg']),
            'pharmaceutical_form' => fake()->optional(0.5)->randomElement(['comprimido', 'cápsula', 'xarope', 'injetável', 'pomada']),
            'posology' => fake()->randomElement([
                '1 comprimido ao dia',
                '1 comprimido 12/12h',
                '1 comprimido 8/8h',
                '2 comprimidos ao dia',
                '1 comprimido à noite',
            ]),
            'quantity' => fake()->optional(0.5)->randomElement(['30 comprimidos', '60 comprimidos', '1 frasco']),
            'treatment_duration' => fake()->optional(0.4)->randomElement(['30 dias', '60 dias', 'uso contínuo']),
            'sort_order' => 0,
        ];
    }

    /**
     * State para receita controlada (item único com dados típicos).
     */
    public function controlled(): self
    {
        return $this->state(fn () => [
            'medication_name' => fake()->randomElement(['Ritalina 10mg', 'Clonazepam 0.5mg', 'Alprazolam 0.25mg']),
            'posology' => '1 comprimido pela manhã',
            'sort_order' => 0,
        ]);
    }

    /**
     * State para criar 3 itens (use com hasMany no Prescription).
     */
    public function multi(): self
    {
        return $this->state(fn () => [
            'sort_order' => fake()->numberBetween(1, 10),
        ]);
    }

    /**
     * Vincula a uma prescription específica.
     */
    public function forPrescription(Prescription $prescription): self
    {
        return $this->state(fn () => ['prescription_id' => $prescription->id]);
    }
}
