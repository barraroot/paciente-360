<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidateMarkdownRequest extends FormRequest
{
    /** Tipo de conteúdo → ability necessária (gate por `type`). */
    private const TYPE_ABILITY = [
        'persona' => 'ai.persona.manage',
        'knowledge_base' => 'ai.knowledge.manage',
        'guardrail' => 'ai.guardrail.manage',
    ];

    public function authorize(): bool
    {
        $ability = self::TYPE_ABILITY[$this->input('type')] ?? null;

        // Sem `type` válido, exige qualquer permissão de gestão de conteúdo de IA.
        if ($ability === null) {
            return collect(self::TYPE_ABILITY)->contains(fn (string $a): bool => $this->user()?->can($a) ?? false);
        }

        return $this->user()?->can($ability) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:100000'],
            'type' => ['nullable', Rule::in(array_keys(self::TYPE_ABILITY))],
        ];
    }
}
