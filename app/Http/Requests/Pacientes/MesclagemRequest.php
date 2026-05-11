<?php

namespace App\Http\Requests\Pacientes;

use App\Models\Paciente;
use Illuminate\Foundation\Http\FormRequest;

/**
 * T115 — Form Request para mesclagem de pacientes (US-3.1).
 *
 * Autoriza via `PacientePolicy::merge`.
 *
 * @see specs/002-crm-pacientes/spec.md US-3.1 (Q2)
 */
class MesclagemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('merge', Paciente::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'paciente_alvo_id' => ['required', 'integer', 'exists:pacientes,id'],
            'pacientes_origem_ids' => ['required', 'array', 'min:1', 'max:5'],
            'pacientes_origem_ids.*' => ['integer', 'exists:pacientes,id', 'different:paciente_alvo_id'],
            'resolucoes' => ['nullable', 'array'],
            'resolucoes.*' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'paciente_alvo_id.required' => 'O paciente alvo é obrigatório.',
            'paciente_alvo_id.exists' => 'Paciente alvo não encontrado.',
            'pacientes_origem_ids.required' => 'Informe pelo menos um paciente de origem.',
            'pacientes_origem_ids.min' => 'Informe pelo menos um paciente de origem.',
            'pacientes_origem_ids.max' => 'Máximo de 5 pacientes de origem por mesclagem.',
            'pacientes_origem_ids.*.different' => 'O paciente de origem não pode ser o mesmo que o alvo.',
        ];
    }
}
