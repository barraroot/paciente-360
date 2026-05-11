<?php

namespace App\Policies;

use App\Models\Paciente;
use App\Models\User;

/**
 * T032 — Policy de autorização para `Paciente` (spec § 2.4).
 *
 * Implementa o gate por ability conforme a tabela em § 2.4 do spec
 * `specs/002-crm-pacientes/spec.md`, mais o override de Super Admin
 * (FR-038): Super Admin **NÃO** acessa endpoints de paciente —
 * mesmo com `Gate::before` global retornando `true`, a `before()` desta
 * policy executa antes e nega o acesso explicitamente para essa role.
 *
 * Isolamento por tenant é garantido upstream pelo `TenantScope` global no
 * Model. Aqui o foco é apenas o gate de abilities + restrição Super Admin.
 */
final class PacientePolicy
{
    /**
     * Override: Super Admin nunca vê dados de paciente (FR-038).
     *
     * Este `before` da Policy roda ANTES do `Gate::before` global
     * configurado em `AppServiceProvider` — a documentação do Laravel
     * (`/docs/13.x/authorization#policy-filters`) confirma: o method
     * `before` da Policy executa antes da resolução do ability, e quando
     * retorna `false` aborta imediatamente.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return false;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('paciente.view');
    }

    public function view(User $user, Paciente $paciente): bool
    {
        return $user->can('paciente.view');
    }

    public function create(User $user): bool
    {
        return $user->can('paciente.create');
    }

    public function update(User $user, Paciente $paciente): bool
    {
        return $user->can('paciente.update');
    }

    public function delete(User $user, Paciente $paciente): bool
    {
        return $user->can('paciente.delete');
    }

    public function import(User $user): bool
    {
        return $user->can('paciente.import');
    }

    public function export(User $user): bool
    {
        return $user->can('paciente.export');
    }

    public function merge(User $user): bool
    {
        return $user->can('paciente.merge');
    }

    /**
     * Anonimização LGPD: tratada como `delete` (FR-035) — apenas
     * Admin Clínica tem a ability `paciente.delete`.
     */
    public function anonymize(User $user, Paciente $paciente): bool
    {
        return $user->can('paciente.delete');
    }
}
