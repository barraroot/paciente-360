<?php

namespace App\Http\Resources\V1;

use App\Support\Cpf\CpfValidator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T116 — Resource completo de `Paciente` (endpoint show/store/update).
 *
 * Formatação:
 *  - CPF: `000.000.000-00` (display BR); null se não preenchido.
 *  - Idade: calculada server-side via `data_nascimento`.
 *  - Relações via `whenLoaded` para evitar N+1.
 *
 * @see specs/002-crm-pacientes/contracts/openapi.yaml § PacienteResource
 */
final class PacienteResource extends JsonResource
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
            'documento_estrangeiro' => $this->documento_estrangeiro,
            'data_nascimento' => $this->data_nascimento?->format('Y-m-d'),
            'idade' => $this->data_nascimento?->age,
            'telefone_primario' => $this->telefone_primario,
            'telefones_secundarios' => $this->telefones_secundarios ?? [],
            'email' => $this->email,
            'endereco' => $this->endereco,
            'status' => $this->status,
            'origem' => $this->origem,
            'origem_detalhe' => $this->origem_detalhe,
            'origem_origem' => $this->origem_origem,
            'profissional_responsavel_id' => $this->profissional_responsavel_id,
            'profissional_responsavel' => $this->whenLoaded(
                'profissionalResponsavel',
                fn () => $this->profissionalResponsavel !== null ? [
                    'id' => $this->profissionalResponsavel->id,
                    'nome' => $this->profissionalResponsavel->name,
                ] : null,
            ),
            'convenio_principal_id' => $this->convenio_principal_id,
            'convenios' => $this->whenLoaded(
                'convenios',
                fn () => PacienteConvenioResource::collection($this->convenios),
            ),
            'tags' => $this->whenLoaded(
                'tags',
                fn () => $this->tags->map(fn ($t) => [
                    'id' => $t->id,
                    'nome' => $t->nome,
                    'cor' => $t->cor,
                    'tipo' => $t->tipo,
                ]),
            ),
            'anonimizado_em' => $this->anonimizado_em?->toIso8601String(),
            'merged_into_paciente_id' => $this->merged_into_paciente_id,
            'merged_at' => $this->merged_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
