<?php

namespace App\Http\Requests\Pacientes;

use App\Models\Anotacao;
use App\Models\Paciente;
use Illuminate\Foundation\Http\FormRequest;

/**
 * T152 — Form Request para criação de anotação.
 *
 * `authorize()` delega à `AnotacaoPolicy::create`, que exige
 * `paciente.view` + `paciente.note.write`.
 *
 * @see App\Policies\AnotacaoPolicy::create
 */
final class CreateAnotacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pacienteId = $this->route('id');
        $paciente = Paciente::findOrFail($pacienteId);

        // Delegates to AnotacaoPolicy::create($user, $paciente)
        return $this->user()->can('create', [Anotacao::class, $paciente]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', 'string', 'in:geral,clinica,comportamental,financeira'],
            'texto' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.required' => 'O tipo da anotação é obrigatório.',
            'tipo.in' => 'Tipo inválido. Use: geral, clinica, comportamental ou financeira.',
            'texto.required' => 'O texto da anotação é obrigatório.',
            'texto.min' => 'O texto deve ter ao menos 1 caractere.',
            'texto.max' => 'O texto não pode ultrapassar 5.000 caracteres.',
        ];
    }
}
