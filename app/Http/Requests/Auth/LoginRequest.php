<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request de autenticação (T103 — FR-021).
 *
 * Validação centralizada aqui; Controller recebe request já validada.
 * Mensagens em pt-BR via `lang/pt_BR/auth.php` (FR-042 — i18n).
 *
 * @property string $email
 * @property string $password
 * @property bool|null $remember
 */
final class LoginRequest extends FormRequest
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
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => __('validation.required', ['attribute' => __('auth.login.email')]),
            'email.email' => __('validation.email', ['attribute' => __('auth.login.email')]),
            'password.required' => __('validation.required', ['attribute' => __('auth.login.password')]),
        ];
    }
}
