<?php

namespace App\Http\Requests\Pacientes;

use App\Models\Paciente;
use Illuminate\Foundation\Http\FormRequest;

/**
 * T119 — Form Request para patch de status de paciente.
 *
 * Autoriza via `PacientePolicy::update`.
 * Transições restritas por `StatusMachineService`.
 *
 * @see App\Services\Pacientes\StatusMachineService
 */
class PatchStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $paciente = Paciente::findOrFail($this->route('id'));

        return $this->user()->can('update', $paciente);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:lead,ativo,inativo,bloqueado'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'O novo status é obrigatório.',
            'status.in' => 'Status inválido. Valores aceitos: lead, ativo, inativo, bloqueado.',
        ];
    }
}
