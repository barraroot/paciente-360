<?php

namespace App\Http\Requests\Inbox;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T238 — FormRequest para renderização de variáveis em resposta rápida.
 *
 * Autorização delegada ao QuickReplyPolicy::render via Gate no controller.
 */
final class RenderQuickReplyRequest extends FormRequest
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
            'conversation_id' => ['required', 'integer', 'exists:messaging_conversations,id'],
        ];
    }
}
