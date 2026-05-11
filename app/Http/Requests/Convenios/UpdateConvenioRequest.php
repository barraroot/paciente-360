<?php

namespace App\Http\Requests\Convenios;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T243 — Form Request para atualização de convênio (US-3.5).
 *
 * Apenas Admin Clínica pode atualizar convênios.
 * Todos os campos são opcionais (PATCH semântico).
 */
class UpdateConvenioRequest extends FormRequest
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
            'nome' => ['sometimes', 'string', 'min:2', 'max:150'],
            'codigo_ans' => ['sometimes', 'nullable', 'string', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
