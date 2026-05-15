<?php

namespace App\Policies\Agenda;

use App\Models\Professional;
use App\Models\User;

/**
 * T038 — ProfessionalSchedule policy (US-6.1, clarify nº 5).
 *
 * Regras:
 *  - viewAny / view: ability `appointment.view` no tenant
 *  - update: `appointment.manage_own_schedule` (só o próprio profissional)
 *           OR `schedule.configure` (admin de qualquer profissional)
 */
class ProfessionalSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('appointment.view');
    }

    public function view(User $user, Professional $professional): bool
    {
        return $user->tenant_id === $professional->tenant_id
            && $user->can('appointment.view');
    }

    public function update(User $user, Professional $professional): bool
    {
        if ($user->tenant_id !== $professional->tenant_id) {
            return false;
        }

        // Admin com schedule.configure — qualquer profissional
        if ($user->can('schedule.configure')) {
            return true;
        }

        // Médico com manage_own_schedule — apenas o próprio (Professional.user_id === user.id)
        if ($user->can('appointment.manage_own_schedule') && (int) $professional->user_id === $user->id) {
            return true;
        }

        return false;
    }
}
