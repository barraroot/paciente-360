<?php

namespace Database\Factories;

use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Cpf\CpfValidator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * T121 — Factory para o Model `Paciente`.
 *
 * Gera dados realistas em pt-BR, incluindo CPF com DV válido calculado
 * algoritmicamente. `tenant_id` é auto-populado pelo `BelongsToTenant`
 * trait quando o container tem tenant resolvido; use `->for(Tenant::factory())`
 * em contextos sem tenant resolvido.
 *
 * @extends Factory<Paciente>
 */
class PacienteFactory extends Factory
{
    protected $model = Paciente::class;

    /**
     * Gera um CPF válido com dígito verificador correto.
     */
    private function gerarCpfValido(): string
    {
        do {
            $base = array_map(fn () => random_int(0, 9), range(1, 9));

            // DV1
            $sum = 0;
            for ($i = 0; $i < 9; $i++) {
                $sum += $base[$i] * (10 - $i);
            }
            $dv1 = ($sum % 11 < 2) ? 0 : 11 - ($sum % 11);

            // DV2
            $sum = 0;
            for ($i = 0; $i < 9; $i++) {
                $sum += $base[$i] * (11 - $i);
            }
            $sum += $dv1 * 2;
            $dv2 = ($sum % 11 < 2) ? 0 : 11 - ($sum % 11);

            $cpf = implode('', $base).$dv1.$dv2;

        } while (! CpfValidator::isValid($cpf));

        return $cpf;
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $faker = fake('pt_BR');

        return [
            'nome' => $faker->name(),
            'cpf' => $this->gerarCpfValido(),
            'data_nascimento' => $faker->dateTimeBetween('-80 years', '-1 year')->format('Y-m-d'),
            'telefone_primario' => $faker->numerify('(##) 9####-####'),
            'telefones_secundarios' => [],
            'email' => $faker->unique()->safeEmail(),
            'status' => 'lead',
            'origem' => $faker->randomElement(['site', 'indicacao', 'whatsapp', 'instagram', 'telefone', 'presencial', 'outro']),
            'origem_origem' => 'manual',
        ];
    }

    /**
     * Estado: paciente Lead (padrão).
     */
    public function lead(): static
    {
        return $this->state(['status' => 'lead']);
    }

    /**
     * Estado: paciente Ativo.
     */
    public function ativo(): static
    {
        return $this->state(['status' => 'ativo']);
    }

    /**
     * Estado: paciente Inativo.
     */
    public function inativo(): static
    {
        return $this->state(['status' => 'inativo']);
    }

    /**
     * Estado: paciente Bloqueado.
     */
    public function bloqueado(): static
    {
        return $this->state(['status' => 'bloqueado']);
    }

    /**
     * Estado: paciente Anonimizado (PII zerado + anonimizado_em preenchido).
     */
    public function anonimizado(): static
    {
        return $this->state([
            'cpf' => null,
            'documento_estrangeiro' => null,
            'email' => null,
            'telefone_primario' => null,
            'telefones_secundarios' => null,
            'endereco' => null,
            'data_nascimento' => null,
            'anonimizado_em' => now(),
        ]);
    }

    /**
     * Estado: vincula profissional responsável.
     */
    public function comProfissional(User $profissional): static
    {
        return $this->state([
            'profissional_responsavel_id' => $profissional->id,
        ]);
    }

    /**
     * Vincula ao tenant especificado (necessário quando não há tenant no container).
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
