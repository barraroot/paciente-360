<?php

namespace App\Policies;

use App\Models\User;

/**
 * Policy de autorização para `AuditLog` (US-2.4 — FR-035/036/037).
 *
 * Acesso ao painel de auditoria do tenant é restrito a:
 *  - `admin-clinica`: dono da operação clínica, audita toda a equipe.
 *  - `financeiro`: investiga ações administrativas/financeiras.
 *  - `super-admin`: bypass via `Gate::before` em AppServiceProvider.
 *
 * Demais roles (médico, atendente, recepcionista) NÃO têm acesso —
 * proteção de privacidade do staff (LGPD — minimização de acesso).
 */
final class AuditLogPolicy
{
    /**
     * Permite listar e exportar audit logs.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin-clinica', 'financeiro']);
    }
}
