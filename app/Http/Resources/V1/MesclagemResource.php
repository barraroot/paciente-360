<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T116 — Resource para `MesclagemPaciente`.
 *
 * @see specs/002-crm-pacientes/contracts/openapi.yaml § MesclagemResource
 */
final class MesclagemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'paciente_alvo_id' => $this->paciente_alvo_id,
            'pacientes_origem_ids' => $this->pacientes_origem_ids,
            'executor_id' => $this->executor_id,
            'resolucoes' => $this->resolucoes,
            'executada_em' => $this->executada_em->toIso8601String(),
            'reversivel_ate' => $this->reversivel_ate->toIso8601String(),
            'revertida_em' => $this->revertida_em?->toIso8601String(),
            'revertida_por_user_id' => $this->revertida_por_user_id,
            'is_reversivel' => $this->isReversivel(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
