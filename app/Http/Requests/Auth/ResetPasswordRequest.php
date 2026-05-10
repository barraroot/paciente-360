<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para o endpoint POST /auth/password/reset (US-2.3 — FR-030, FR-031).
 *
 * Valida token (64 chars hex), e-mail e política de senha forte
 * (≥ 8 caracteres — FR-031). A verificação de existência do token e
 * do usuário é responsabilidade do `PasswordResetService`.
 */
final class ResetPasswordRequest extends FormRequest
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
            'token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }
}
