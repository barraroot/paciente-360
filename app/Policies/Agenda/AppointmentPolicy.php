<?php

namespace App\Policies\Agenda;

use App\Models\Agenda\Appointment;
use App\Models\User;

/**
 * T075 — AppointmentPolicy (US-6.3 / 6.5).
 */
class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('appointment.view');
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->tenant_id === $appointment->tenant_id && $user->can('appointment.view');
    }

    public function create(User $user): bool
    {
        return $user->can('appointment.create');
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $user->tenant_id === $appointment->tenant_id && $user->can('appointment.update');
    }

    public function reschedule(User $user, Appointment $appointment): bool
    {
        return $user->tenant_id === $appointment->tenant_id && $user->can('appointment.update');
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $user->tenant_id === $appointment->tenant_id && $user->can('appointment.cancel');
    }
}
