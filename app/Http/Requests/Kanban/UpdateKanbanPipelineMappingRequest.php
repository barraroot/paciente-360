<?php

declare(strict_types=1);

namespace App\Http\Requests\Kanban;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateKanbanPipelineMappingRequest extends FormRequest
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
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
