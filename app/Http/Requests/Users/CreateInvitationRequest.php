<?php

namespace App\Http\Requests\Users;

use App\Services\Users\InvitationService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida e autoriza criação de convite de usuário interno (FR-025).
 *
 * Autorização: apenas `admin-clinica` pode convidar.
 * Validação: e-mail válido + role na allowlist.
 */
final class CreateInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin-clinica') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $roles = implode(',', InvitationService::ALLOWED_ROLES);

        return [
            'email' => ['required', 'email', 'max:254'],
            'intended_role' => ['required', 'string', "in:{$roles}"],
        ];
    }
}
