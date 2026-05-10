<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para o endpoint POST /auth/password/forgot (US-2.3 — FR-030).
 *
 * Valida apenas o formato do e-mail — não revela se o e-mail existe na base
 * (FR-032: resposta genérica independente do resultado).
 */
final class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:254'],
        ];
    }
}
