<?php

namespace App\Http\Requests\Inbox;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * T263 — FormRequest para PATCH /inbox/presence/me (NC-6.b).
 *
 * Permite atualizar preferências de notificação e limite de conversas simultâneas.
 */
final class UpdatePresenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inbox.view') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'max_concurrent_conversations' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'notification_preferences' => ['sometimes', 'array'],
            'notification_preferences.new_conversation' => ['sometimes', 'boolean'],
            'notification_preferences.new_message' => ['sometimes', 'boolean'],
            'notification_preferences.assignment' => ['sometimes', 'boolean'],
        ];
    }
}
