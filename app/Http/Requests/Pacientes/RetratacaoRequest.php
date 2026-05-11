<?php

namespace App\Http\Requests\Pacientes;

use App\Models\Anotacao;
use App\Models\Paciente;
use Illuminate\Foundation\Http\FormRequest;

/**
 * T152 — Form Request para retratação de anotação.
 *
 * A autorização verifica `paciente.note.write` via `AnotacaoPolicy::create`
 * no paciente alvo, seguindo o mesmo gate de criação.
 *
 * @see App\Policies\AnotacaoPolicy::create
 */
final class RetratacaoRequest extends FormRequest
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
            'texto' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'texto.required' => 'O texto da retratação é obrigatório.',
            'texto.min' => 'A retratação deve ter ao menos 10 caracteres.',
            'texto.max' => 'O texto não pode ultrapassar 5.000 caracteres.',
        ];
    }
}
