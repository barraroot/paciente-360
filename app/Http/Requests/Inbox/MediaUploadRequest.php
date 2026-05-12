<?php

namespace App\Http\Requests\Inbox;

use App\Domain\Messaging\Message\Services\MediaUploadService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * T250 — FormRequest para solicitação de upload pré-assinado de mídia (NC-9).
 *
 * Valida shape conforme openapi.yaml paths `/inbox/media/upload`.
 * A validação de MIME whitelist e size limits por tipo é feita em
 * `MediaUploadService::requestUpload` para centralizar regras de negócio.
 */
final class MediaUploadRequest extends FormRequest
{
    /**
     * Requer ability `inbox.respond`.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('inbox.respond') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allowedMimes = implode(',', array_keys(MediaUploadService::MIME_LIMITS));

        return [
            'conversation_id' => ['required', 'integer', 'min:1'],
            'mime_type' => ['required', 'string', 'in:'.$allowedMimes],
            'size_bytes' => ['required', 'integer', 'min:1'],
            'original_filename' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mime_type.in' => 'MIME type não é permitido. Tipos aceitos: image/jpeg, image/png, image/webp, audio/mpeg, audio/ogg, audio/mp4, video/mp4, application/pdf.',
        ];
    }
}
