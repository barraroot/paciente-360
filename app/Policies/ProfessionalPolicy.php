<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Professional;
use App\Models\User;

/**
 * **T007 (Spec 012)** — Policy gates do CRUD de Professional.
 *
 * `manage` (criar/editar/desativar/reativar): exige permission `professional.manage`,
 * concedida apenas ao role `admin-clinica` na seeder.
 *
 * `viewAny`: aberto para qualquer usuário autenticado — necessário para selects
 * de seleção em outras telas (criação de Appointment, atribuição de paciente, etc.).
 *
 * @see specs/012-professionals-management/research.md R3
 */
final class ProfessionalPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Professional $professional): bool
    {
        return true;
    }

    public function manage(User $user): bool
    {
        return $user->can('professional.manage');
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Professional $professional): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, Professional $professional): bool
    {
        return $this->manage($user);
    }
}
