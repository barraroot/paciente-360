<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T116 — Resource para `Convenio` (catálogo por tenant).
 *
 * @see specs/002-crm-pacientes/contracts/openapi.yaml § ConvenioResource
 */
final class ConvenioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'codigo_ans' => $this->codigo_ans,
            'is_active' => $this->is_active,
            'pacientes_count' => $this->when(
                isset($this->pacientes_count),
                fn () => $this->pacientes_count,
            ),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
