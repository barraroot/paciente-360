<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida dados de aceite de convite (FR-025, FR-031).
 *
 * Endpoint público: `authorize()` retorna `true`.
 * Política de senha: mínimo 8 caracteres, ao menos 1 maiúscula e 1 número.
 */
final class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'min:32'],
            'name' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'min:8', 'regex:/[A-Z]/', 'regex:/[0-9]/'],
        ];
    }
}
