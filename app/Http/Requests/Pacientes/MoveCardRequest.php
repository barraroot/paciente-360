<?php

namespace App\Http\Requests\Pacientes;

use App\Models\FunilColuna;
use Illuminate\Foundation\Http\FormRequest;

/**
 * T212 — Form Request para mover card no Kanban (US-3.4).
 *
 * Valida coluna de destino, posição fracionária e motivo (obrigatório
 * somente quando a coluna tem `motivo_obrigatorio = true`, verificado
 * no `FunilService` após esta validação estrutural).
 *
 * @see App\Services\Funil\FunilService::moveCard()
 */
class MoveCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('move', FunilColuna::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'coluna_id' => ['required', 'integer', 'exists:funil_colunas,id'],
            'posicao' => ['nullable', 'numeric', 'min:0'],
            'motivo' => ['nullable', 'string', 'in:sem_interesse,sem_retorno,preco,outro'],
            'motivo_outro' => ['nullable', 'required_if:motivo,outro', 'string', 'min:10', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'coluna_id.required' => 'Informe a coluna de destino.',
            'coluna_id.exists' => 'Coluna de destino inválida.',
            'motivo.in' => 'Motivo inválido.',
            'motivo_outro.required_if' => __('paciente.funil.motivo_outro_obrigatorio'),
            'motivo_outro.min' => __('paciente.funil.motivo_outro_obrigatorio'),
        ];
    }
}
