<?php

namespace App\Http\Requests\Inbox;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T074 — FormRequest para reconectar um canal (POST /api/v1/inbox/channels/{id}/reconnect).
 */
class ReconnectChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('channel.connect') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'credentials_override' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
