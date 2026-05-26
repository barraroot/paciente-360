<?php

namespace App\Http\Requests\Ai;

use App\Domain\Ai\Services\MarkdownSanitizerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuardrailRequest extends FormRequest
{
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
            // `is_active` muda só via activate/deactivate (endpoints dedicados).
            'is_active' => ['prohibited'],
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', Rule::in(StoreGuardrailRequest::CATEGORIES)],
            'markdown_content' => ['sometimes', 'string', 'max:50000'],
        ];
    }
}
