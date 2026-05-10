<?php

namespace App\Policies;

use App\Models\User;

/**
 * Policy de autorização para ações do wizard de onboarding.
 *
 * Apenas usuários com role `admin-clinica` ou `super-admin` podem
 * gerenciar o onboarding (completar ou pular etapas).
 *
 * @see App\Http\Controllers\Api\V1\Onboarding\OnboardingController
 */
final class OnboardingPolicy
{
    /**
     * Determina se o usuário pode gerenciar o wizard de onboarding.
     * Abrange os verbos `complete` e `skip`.
     */
    public function manage(User $user): bool
    {
        return $user->hasRole('admin-clinica') || $user->hasRole('super-admin');
    }
}
