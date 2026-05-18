<?php

namespace App\Policies;

use App\Domain\Prescription\Prescription\Prescription;
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

        // For controlled prescriptions, masking is handled in PrescriptionResource.
        // All users with prescription.view within the tenant can VIEW the record
        // (masked or full — depends on viewControlled ability + is emissor).
        // 403 is only returned when user lacks prescription.view entirely.
        return true;
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
        // Cancelled prescriptions are immutable (AC-8.1.7)
        if ($prescription->isCancelled()) {
            return false;
        }

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
