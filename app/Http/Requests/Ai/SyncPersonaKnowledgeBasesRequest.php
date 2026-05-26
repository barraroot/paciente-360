<?php

namespace App\Http\Requests\Ai;

use App\Domain\Ai\KnowledgeBase\Models\AiKnowledgeBase;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SyncPersonaKnowledgeBasesRequest extends FormRequest
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
            'knowledge_base_ids' => ['present', 'array'],
            'knowledge_base_ids.*' => ['integer'],
        ];
    }

    /**
     * Co-tenancy (G2/Princípio II): toda base referenciada deve pertencer ao
     * tenant atual. O global scope garante que IDs de outra clínica não sejam
     * encontrados — qualquer divergência é rejeitada.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ids = array_values(array_unique(array_map('intval', (array) $this->input('knowledge_base_ids', []))));
            if ($ids === []) {
                return;
            }

            $found = AiKnowledgeBase::query()->whereIn('id', $ids)->pluck('id')->all();

            if (count($found) !== count($ids)) {
                $validator->errors()->add('knowledge_base_ids', __('ai.knowledge_base.invalid_association'));
            }
        });
    }
}
