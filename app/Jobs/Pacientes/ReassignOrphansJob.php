<?php

namespace App\Jobs\Pacientes;

use App\Jobs\TenantAwareJob;
use App\Models\Paciente;

/**
 * T260 — Job que reatribui pacientes órfãos após desativação de profissional.
 *
 * Quando um profissional é desativado, `ProfessionalDeactivatedListener` cria
 * uma `TarefaReatribuicao` e dispara este job. O job itera a lista de pacientes
 * vinculados ao profissional e seta `profissional_responsavel_id = null`.
 *
 * Cada update é auditado via evento `PacienteAtualizado`.
 */
class ReassignOrphansJob extends TenantAwareJob
{
    /**
     * @param array<int> $pacienteIds
     */
    public function __construct(
        public array $pacienteIds,
    ) {
        parent::__construct();
    }

    protected function run(): void
    {
        foreach ($this->pacienteIds as $pacienteId) {
            $paciente = Paciente::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $this->tenantId)
                ->find($pacienteId);

            if ($paciente === null) {
                continue;
            }

            $paciente->update([
                'profissional_responsavel_id' => null,
            ]);
        }
    }
}
