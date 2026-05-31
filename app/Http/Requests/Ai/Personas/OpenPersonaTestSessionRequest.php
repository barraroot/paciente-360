<?php

declare(strict_types=1);

namespace App\Http\Requests\Ai\Personas;

use Illuminate\Foundation\Http\FormRequest;

final class OpenPersonaTestSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ai.persona.test') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'use_draft' => ['sometimes', 'boolean'],
            'persona_draft' => ['required_if:use_draft,true', 'nullable', 'array'],
            'persona_draft.name' => ['required_if:use_draft,true', 'string', 'min:2'],
            'persona_draft.markdown_content' => ['required_if:use_draft,true', 'string'],
        ];
    }
}
