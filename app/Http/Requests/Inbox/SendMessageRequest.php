<?php

namespace App\Http\Requests\Inbox;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T116 — FormRequest para envio de mensagem outbound.
 *
 * Valida header Idempotency-Key, content_type e campos específicos
 * por tipo (body, media_token, template).
 *
 * Autorização delegada à ConversationPolicy::respond via controller.
 */
final class SendMessageRequest extends FormRequest
{
    /**
     * Autorização tratada pelo Gate no controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $contentType = $this->input('content_type');

        return [
            'idempotency_key' => ['required', 'string', 'min:1'],
            'content_type' => ['required', 'string', 'in:text,template,image,audio,video,document'],
            'body' => [
                'sometimes',
                'nullable',
                'string',
                'max:4096',
                ...(in_array($contentType, ['text'], true) ? ['required'] : []),
            ],
            'media_token' => [
                'sometimes',
                'nullable',
                'string',
                ...(in_array($contentType, ['image', 'audio', 'video', 'document'], true) ? ['required'] : []),
            ],
            'template' => [
                'sometimes',
                'nullable',
                'array',
                ...($contentType === 'template' ? ['required'] : []),
            ],
            'template.provider_template_id' => [
                'sometimes',
                'string',
                ...($contentType === 'template' ? ['required'] : []),
            ],
            'template.variables' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * Prepara os dados para validação — extrai Idempotency-Key do header.
     */
    protected function prepareForValidation(): void
    {
        $idempotencyKey = $this->header('Idempotency-Key');

        $this->merge([
            'idempotency_key' => $idempotencyKey,
        ]);
    }
}
