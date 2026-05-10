<?php

namespace App\Http\Requests\Users;

use App\Services\Users\InvitationService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida e autoriza edição de usuário interno (FR-026).
 *
 * Autorização: apenas `admin-clinica` pode alterar usuários.
 * Todos os campos são opcionais (PATCH semântico).
 */
final class UserPatchRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:150'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', "in:{$roles}"],
        ];
    }
}
