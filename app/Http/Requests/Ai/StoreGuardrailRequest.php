<?php

namespace App\Http\Requests\Ai;

use App\Domain\Ai\Services\MarkdownSanitizerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuardrailRequest extends FormRequest
{
    /** Categorias previstas no data-model (rótulo livre, mas guiado). */
    public const CATEGORIES = [
        'seguranca',
        'lgpd',
        'atendimento_medico',
        'encaminhamento',
        'tom_de_voz',
        'restricoes_comerciais',
        'emergencia',
        'privacidade',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('ai.guardrail.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $value = $this->input('markdown_content');
        if (is_string($value) && $value !== '') {
            $this->merge(['markdown_content' => app(MarkdownSanitizerService::class)->sanitize($value)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', Rule::in(self::CATEGORIES)],
            'markdown_content' => ['required', 'string', 'max:50000'],
        ];
    }
}
