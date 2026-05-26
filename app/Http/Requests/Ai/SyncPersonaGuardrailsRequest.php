<?php

namespace App\Http\Requests\Ai;

use App\Domain\Ai\Guardrail\Models\AiGuardrail;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SyncPersonaGuardrailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ai.persona.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'guardrail_ids' => ['present', 'array'],
            'guardrail_ids.*' => ['integer'],
        ];
    }

    /**
     * Co-tenancy (G2/Princípio II): todo guardrail referenciado deve pertencer
     * ao tenant atual — o global scope garante que IDs de outra clínica não
     * sejam encontrados.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ids = array_values(array_unique(array_map('intval', (array) $this->input('guardrail_ids', []))));
            if ($ids === []) {
                return;
            }

            $found = AiGuardrail::query()->whereIn('id', $ids)->pluck('id')->all();

            if (count($found) !== count($ids)) {
                $validator->errors()->add('guardrail_ids', __('ai.guardrail.invalid_association'));
            }
        });
    }
}
