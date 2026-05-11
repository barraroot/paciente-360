<?php

namespace App\Http\Requests\Pacientes;

use App\Models\FunilColuna;
use Illuminate\Foundation\Http\FormRequest;

/**
 * T214 — Form Request para atualizar configuração de coluna do funil (US-3.4).
 *
 * Admin pode renomear, alterar cor, `motivo_obrigatorio` e `posicao`.
 * O slug é imutável — determinado na criação e nunca alterado.
 */
class UpdateColunaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('configure', FunilColuna::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'required', 'string', 'max:80'],
            'cor' => ['sometimes', 'nullable', 'string', 'max:7'],
            'motivo_obrigatorio' => ['sometimes', 'boolean'],
            'posicao' => ['sometimes', 'required', 'integer', 'min:1'],
        ];
    }
}
