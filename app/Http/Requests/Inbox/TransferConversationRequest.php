<?php

namespace App\Http\Requests\Inbox;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * T152 — FormRequest para transferência de conversa com nota obrigatória.
 *
 * Requer `inbox.transfer` ability.
 * Aceita `user_id` (int) OU `role` (string) — mutuamente exclusivos.
 * `transfer_note` é obrigatória, mínimo 10 chars (AC-4.5.3, NC-7.a).
 * Roles permitidas: medico, atendente, recepcionista, admin-clinica.
 * financeiro excluído (sem inbox.view — AC-4.5.7).
 */
final class TransferConversationRequest extends FormRequest
{
    /** @var list<string> */
    private const ALLOWED_ROLES = ['medico', 'atendente', 'recepcionista', 'admin-clinica'];

    public function authorize(): bool
    {
        return $this->user()?->can('inbox.transfer') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exclude_with:role'],
            'role' => ['nullable', 'string', Rule::in(self::ALLOWED_ROLES), 'exclude_with:user_id'],
            'transfer_note' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
