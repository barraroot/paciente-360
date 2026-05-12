<?php

namespace App\Http\Requests\Inbox;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T074 — FormRequest para conectar um canal externo (POST /api/v1/inbox/channels).
 *
 * Valida tipo, nome e credenciais específicas do provider.
 * Autorização: ability `channel.connect`.
 */
class ConnectChannelRequest extends FormRequest
{
    /**
     * Verifica se o usuário tem permissão para conectar canais.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('channel.connect') ?? false;
    }

    /**
     * Normaliza o input antes da validação para tolerar formatos comuns
     * que o atendente possa digitar (sem prefixo `whatsapp:`, com espaços, etc.).
     */
    protected function prepareForValidation(): void
    {
        $sender = $this->input('credentials.whatsapp_sender');

        if (is_string($sender) && $sender !== '') {
            $cleaned = preg_replace('/\s+/', '', $sender);

            if (! str_starts_with($cleaned, 'whatsapp:')) {
                $cleaned = 'whatsapp:'.ltrim($cleaned, ':');
            }

            $this->merge([
                'credentials' => array_merge($this->input('credentials', []), [
                    'whatsapp_sender' => $cleaned,
                ]),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'type' => ['required', 'string', 'in:whatsapp,instagram,web'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'credentials' => ['required', 'array'],
        ];

        $type = $this->input('type');

        if ($type === 'whatsapp') {
            $rules['credentials.account_sid'] = ['required', 'string', 'regex:/^AC[a-f0-9]{32}$/i'];
            $rules['credentials.auth_token'] = ['required', 'string'];
            $rules['credentials.messaging_service_sid'] = ['required', 'string', 'regex:/^MG[a-f0-9]{32}$/i'];
            $rules['credentials.whatsapp_sender'] = ['required', 'string', 'regex:/^whatsapp:\+\d{8,15}$/'];
        }

        return $rules;
    }
}
