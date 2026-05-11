<?php

namespace Database\Factories;

use App\Models\Anotacao;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * T156 — Factory para o Model `Anotacao`.
 *
 * Anotações são imutáveis, portanto a factory sempre cria novos registros
 * via INSERT — nunca atualiza. `tenant_id` deve ser passado pelo caller
 * via `->state(['tenant_id' => X])` ou `->for(Tenant::factory())`.
 *
 * Estados disponíveis: `geral()`, `clinica()`, `comportamental()`,
 * `financeira()`, `retratacaoDe(Anotacao $original)`.
 *
 * @extends Factory<Anotacao>
 */
class AnotacaoFactory extends Factory
{
    protected $model = Anotacao::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'paciente_id' => Paciente::factory(),
            'tipo' => fake()->randomElement(['geral', 'clinica', 'comportamental', 'financeira']),
            'texto' => fake()->paragraphs(2, true),
            'autor_id' => null,
            'retratacao_de_anotacao_id' => null,
        ];
    }

    public function geral(): static
    {
        return $this->state(['tipo' => 'geral']);
    }

    public function clinica(): static
    {
        return $this->state(['tipo' => 'clinica']);
    }

    public function comportamental(): static
    {
        return $this->state(['tipo' => 'comportamental']);
    }

    public function financeira(): static
    {
        return $this->state(['tipo' => 'financeira']);
    }

    public function retratacaoDe(Anotacao $original): static
    {
        return $this->state([
            'tenant_id' => $original->tenant_id,
            'paciente_id' => $original->paciente_id,
            'tipo' => $original->tipo,
            'retratacao_de_anotacao_id' => $original->id,
        ]);
    }

    /**
     * Vincula a anotação a um autor (User) existente.
     */
    public function comAutor(User $autor): static
    {
        return $this->state([
            'autor_id' => $autor->id,
            'tenant_id' => $autor->tenant_id,
        ]);
    }
}
