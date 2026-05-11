<?php

namespace App\Http\Requests\Convenios;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T243 — Form Request para criação de convênio (US-3.5).
 *
 * Apenas Admin Clínica pode criar convênios (catálogo por tenant).
 */
class CreateConvenioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('admin-clinica');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'min:2', 'max:150'],
            'codigo_ans' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'O nome do convênio é obrigatório.',
            'nome.min' => 'O nome do convênio deve ter no mínimo 2 caracteres.',
            'nome.max' => 'O nome do convênio deve ter no máximo 150 caracteres.',
            'codigo_ans.max' => 'O código ANS deve ter no máximo 10 caracteres.',
        ];
    }
}
