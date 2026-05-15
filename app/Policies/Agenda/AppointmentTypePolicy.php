<?php

namespace App\Policies\Agenda;

use App\Models\Agenda\AppointmentType;
use App\Models\User;

/**
 * T052 — AppointmentType policy (US-6.2).
 *
 * Regras:
 *  - viewAny / view: ability `appointment.view`
 *  - create/update/delete: ability `appointment_type.manage` (default: admin-clinica)
 */
class AppointmentTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('appointment.view');
    }

    public function view(User $user, AppointmentType $type): bool
    {
        return $user->tenant_id === $type->tenant_id && $user->can('appointment.view');
    }

    public function create(User $user): bool
    {
        return $user->can('appointment_type.manage');
    }

    public function update(User $user, AppointmentType $type): bool
    {
        return $user->tenant_id === $type->tenant_id && $user->can('appointment_type.manage');
    }

    public function delete(User $user, AppointmentType $type): bool
    {
        return $user->tenant_id === $type->tenant_id && $user->can('appointment_type.manage');
    }
}
