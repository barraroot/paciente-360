<?php

namespace Database\Factories;

use App\Models\Convenio;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * T122 — Factory para o Model `Convenio`.
 *
 * Gera convênios realistas do mercado de saúde suplementar brasileiro.
 *
 * @extends Factory<Convenio>
 */
class ConvenioFactory extends Factory
{
    protected $model = Convenio::class;

    /** @var list<string> */
    private const NOMES = [
        'Unimed',
        'Amil',
        'Bradesco Saúde',
        'SulAmérica',
        'Hapvida',
        'NotreDame Intermédica',
        'Porto Seguro Saúde',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->randomElement(self::NOMES).' '.fake()->numerify('###'),
            'codigo_ans' => fake()->boolean(70) ? fake()->numerify('######') : null,
            'is_active' => true,
        ];
    }

    /**
     * Estado: convênio inativo.
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * Vincula ao tenant especificado.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
