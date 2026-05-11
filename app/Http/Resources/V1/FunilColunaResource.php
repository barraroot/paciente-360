<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T213 — Resource de serialização de `FunilColuna` (US-3.4).
 *
 * Inclui `pacientes_count` calculado server-side via `withCount` ou
 * `$this->whenCounted()`.
 *
 * @see specs/002-crm-pacientes/contracts/openapi.yaml
 */
final class FunilColunaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'nome' => $this->nome,
            'posicao' => $this->posicao,
            'cor' => $this->cor,
            'is_terminal' => $this->is_terminal,
            'motivo_obrigatorio' => $this->motivo_obrigatorio,
            'is_system' => $this->is_system,
            'pacientes_count' => $this->whenCounted('pacientes'),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
