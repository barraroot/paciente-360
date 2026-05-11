<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T153 — Resource para `Anotacao`.
 *
 * Inclui autor (id + nome), retratacoes (IDs das retratações vinculadas
 * se for anotação original) e flag `eh_retratacao`.
 *
 * @see specs/002-crm-pacientes/contracts/openapi.yaml § AnotacaoResource
 */
final class AnotacaoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'paciente_id' => $this->paciente_id,
            'tipo' => $this->tipo,
            'texto' => $this->texto,
            'autor' => $this->when(
                $this->relationLoaded('autor') && $this->autor !== null,
                fn () => [
                    'id' => $this->autor->id,
                    'nome' => $this->autor->name,
                ],
                fn () => ['id' => $this->autor_id, 'nome' => null],
            ),
            'retratacao_de_anotacao_id' => $this->retratacao_de_anotacao_id,
            'eh_retratacao' => $this->retratacao_de_anotacao_id !== null,
            'retratacoes' => $this->when(
                $this->relationLoaded('retratacoes'),
                fn () => $this->retratacoes->pluck('id')->toArray(),
                [],
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
