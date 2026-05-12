<?php

namespace App\Http\Requests\Inbox;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T238 — FormRequest para atualização de resposta rápida (todos os campos opcionais).
 *
 * Autorização delegada ao QuickReplyPolicy::update via Gate no controller.
 */
final class UpdateQuickReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorização tratada pelo Gate no controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shortcut' => ['sometimes', 'string', 'min:2', 'max:50', 'regex:/^\/[a-zA-Z0-9_-]+$/'],
            'content' => ['sometimes', 'string', 'min:1', 'max:4096'],
        ];
    }
}
