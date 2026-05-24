<?php

namespace App\Listeners\Professional;

use App\Events\Professional\ProfessionalDeactivated;
use App\Jobs\Pacientes\ReassignOrphansJob;
use App\Models\Paciente;
use App\Models\TarefaReatribuicao;

/**
 * T260 — Listener para o evento `ProfessionalDeactivated`.
 *
 * Quando um profissional é desativado, este listener:
 * 1. Encontra todos os pacientes vinculados ao profissional.
 * 2. Cria um registro em `tarefas_reatribuicao` com a lista de pacientes órfãos.
 * 3. Dispara o job `ReassignOrphansJob` que atualiza os pacientes.
 */
class ProfessionalDeactivatedListener
{
    public function handle(ProfessionalDeactivated $event): void
    {
        // Busca todos os pacientes vinculados a este profissional
        $pacienteIds = Paciente::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $event->professional->tenant_id)
            ->where('profissional_responsavel_id', $event->professional->id)
            ->pluck('id')
            ->toArray();

        if (empty($pacienteIds)) {
            return;
        }

        // Verifica se já existe uma tarefa para este profissional
        $existingTask = TarefaReatribuicao::query()
            ->where('tenant_id', $event->professional->tenant_id)
            ->where('profissional_desativado_id', $event->professional->id)
            ->where('concluida_em', null)
            ->exists();

        if ($existingTask) {
            return;
        }

        // Cria tarefa de reatribuição
        TarefaReatribuicao::create([
            'tenant_id' => $event->professional->tenant_id,
            'profissional_desativado_id' => $event->professional->id,
            'pacientes_orfaos_ids' => $pacienteIds,
            'total_pacientes' => count($pacienteIds),
            'criada_em' => now(),
        ]);

        // Dispara o job de reatribuição na fila padrão. NÃO usar
        // `config('queue.default')` aqui: isso retorna o nome da CONEXÃO
        // ('redis'), não da fila — o job acabaria numa fila "redis" que
        // nenhum supervisor do Horizon consome (job preso indefinidamente).
        ReassignOrphansJob::dispatch($pacienteIds)
            ->onQueue('default');
    }
}
