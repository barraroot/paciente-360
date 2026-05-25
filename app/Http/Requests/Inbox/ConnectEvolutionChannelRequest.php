<?php

namespace App\Http\Requests\Inbox;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Feature 014 — Conectar um canal WhatsApp NÃO oficial (Evolution) por QR Code.
 *
 * Não exige credenciais (servidor é nosso); apenas o nome do canal.
 * Autorização: ability `channel.connect`.
 */
class ConnectEvolutionChannelRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }
}
