<?php

declare(strict_types=1);

namespace App\Http\Requests\Kanban;

use Illuminate\Foundation\Http\FormRequest;

final class PromoteToKanbanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('paciente.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'funil_coluna_id' => ['required', 'integer', 'exists:funil_colunas,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
