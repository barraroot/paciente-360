<?php

declare(strict_types=1);

namespace App\Http\Requests\Ai\Personas;

use Illuminate\Foundation\Http\FormRequest;

final class SendPersonaTestMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ai.persona.test') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content_type' => ['required', 'string', 'in:text,audio'],
            'text' => ['required_if:content_type,text', 'string', 'min:1', 'max:5000'],
            'audio_base64' => ['required_if:content_type,audio', 'string'],
            'audio_mime_type' => ['required_if:content_type,audio', 'string'],
        ];
    }
}
