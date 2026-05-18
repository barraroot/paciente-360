<?php

namespace App\Policies;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionType;
use App\Models\User;

final class PrescriptionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return false;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('prescription.view');
    }

    public function view(User $user, Prescription $prescription): bool
    {
        if ($user->tenant_id !== $prescription->tenant_id || ! $user->can('prescription.view')) {
            return false;
        }

        if ($prescription->type !== PrescriptionType::Controlled) {
            return true;
        }

        return $this->viewControlled($user, $prescription);
    }

    public function viewControlled(User $user, Prescription $prescription): bool
    {
        if ($user->tenant_id !== $prescription->tenant_id || ! $user->can('prescription.view_controlled')) {
            return false;
        }

        if ($user->hasRole('admin-clinica')) {
            return true;
        }

        return $prescription->professional_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('prescription.create');
    }

    public function update(User $user, Prescription $prescription): bool
    {
        return $user->tenant_id === $prescription->tenant_id
            && $prescription->professional_id === $user->id
            && $user->can('prescription.update');
    }

    public function cancel(User $user, Prescription $prescription): bool
    {
        if ($user->tenant_id !== $prescription->tenant_id || ! $user->can('prescription.cancel')) {
            return false;
        }

        if ($user->hasRole('admin-clinica')) {
            return true;
        }

        return $prescription->professional_id === $user->id;
    }

    public function export(User $user): bool
    {
        return $user->can('prescription.export');
    }
}
