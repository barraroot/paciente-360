<?php

namespace Database\Factories;

use App\Models\MesclagemPaciente;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MesclagemPaciente>
 */
class MesclagemPacienteFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::first() ?? Tenant::factory()->create();
        $user = User::first() ?? User::factory()->create();

        return [
            'tenant_id' => $tenant->id,
            'paciente_alvo_id' => Paciente::factory()->forTenant($tenant),
            'pacientes_origem_ids' => [Paciente::factory()->forTenant($tenant)->create()->id],
            'executor_id' => $user->id,
            'reversivel_ate' => now()->addDays(30),
            'revertida_em' => null,
            'snapshot_pre_merge' => [
                'nome' => 'Old Name',
                'telefone_primario' => '31999999999',
            ],
            'resolucoes' => [],
            'executada_em' => now(),
        ];
    }

    public function forTenant(Tenant $tenant): self
    {
        return $this->state([
            'tenant_id' => $tenant->id,
        ]);
    }
}
