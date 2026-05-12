<?php

namespace App\Http\Requests\Inbox;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T074 — FormRequest para atualizar metadados de um canal (PATCH /api/v1/inbox/channels/{id}).
 */
class UpdateChannelRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'min:2', 'max:100'],
            'auto_send_disabled' => ['sometimes', 'boolean'],
        ];
    }
}
