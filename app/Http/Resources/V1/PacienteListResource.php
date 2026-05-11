<?php

namespace App\Http\Resources\V1;

use App\Support\Cpf\CpfValidator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T116 — Resource de listagem de `Paciente` (endpoint index).
 *
 * Subset do `PacienteResource` — sem endereço completo nem relações pesadas.
 * Otimizado para exibição em tabelas e kanban.
 *
 * @see App\Http\Resources\V1\PacienteResource
 */
final class PacienteListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'cpf' => $this->cpf !== null ? CpfValidator::format($this->cpf) : null,
            'telefone_primario' => $this->telefone_primario,
            'email' => $this->email,
            'status' => $this->status,
            'origem' => $this->origem,
            'data_nascimento' => $this->data_nascimento?->format('Y-m-d'),
            'idade' => $this->data_nascimento?->age,
            'profissional_responsavel_id' => $this->profissional_responsavel_id,
            'convenio_principal_id' => $this->convenio_principal_id,
            'tags' => $this->whenLoaded(
                'tags',
                fn () => $this->tags->map(fn ($t) => [
                    'id' => $t->id,
                    'nome' => $t->nome,
                    'cor' => $t->cor,
                ]),
            ),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
