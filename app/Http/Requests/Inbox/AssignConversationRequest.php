<?php

namespace App\Http\Requests\Inbox;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * T152 — FormRequest para atribuição manual/auto de conversa.
 *
 * Requer `inbox.assign` ability.
 * Aceita `user_id` (int) OU `auto` (bool) — mutuamente exclusivos.
 * Opcionalmente aceita `strategy` para forçar uma estratégia de auto-atribuição.
 */
final class AssignConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inbox.assign') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exclude_with:auto'],
            'auto' => ['nullable', 'boolean', 'exclude_with:user_id'],
            'strategy' => ['nullable', 'string', Rule::in(['round_robin', 'patient_owner'])],
        ];
    }
}
