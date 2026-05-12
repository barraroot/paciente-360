<?php

namespace App\Http\Requests\Inbox;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * T238 — FormRequest para criação de resposta rápida.
 *
 * Autorização:
 *  - scope=tenant → exige ability `quick_reply.manage`
 *  - scope=private → qualquer user com ability `inbox.respond`
 */
final class CreateQuickReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($this->input('scope') === 'tenant') {
            return $user->can('quick_reply.manage');
        }

        return $user->can('inbox.respond');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', 'string', Rule::in(['tenant', 'private'])],
            'shortcut' => ['required', 'string', 'min:2', 'max:50', 'regex:/^\/[a-zA-Z0-9_-]+$/'],
            'content' => ['required', 'string', 'min:1', 'max:4096'],
        ];
    }
}
