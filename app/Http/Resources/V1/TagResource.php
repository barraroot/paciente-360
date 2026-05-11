<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T244 — Resource para `Tag`.
 *
 * `pacientes_count` exposto via `whenLoaded` para evitar N+1.
 */
final class TagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'nome_normalizado' => $this->nome_normalizado,
            'tipo' => $this->tipo,
            'cor' => $this->cor,
            'descricao' => $this->descricao,
            'pacientes_count' => $this->whenLoaded(
                'pacientes',
                fn () => $this->pacientes->count(),
                $this->when(
                    isset($this->pacientes_count),
                    fn () => $this->pacientes_count,
                ),
            ),
        ];
    }
}
