<?php

declare(strict_types=1);

namespace App\Policies\Panel;

use App\Models\User;

/**
 * **T007 (Fase 10 — Spec 010)** — Policy gates do Dashboard Home.
 *
 * Pontos de auth dentro da feature:
 *   - canSeeClinicScope: controla visibilidade do toggle "Visão da clínica"
 *     e força `scope_applied='user'` quando o usuário pede `clinic` sem
 *     permissão (Q1 / FR-021 / FR-025).
 *   - canSeeWebhookDlqAlerts: filtra alertas de tipo `webhook_dlq` para
 *     usuários sem `webhook.manage` (FR-013).
 *   - canSeeConfirmationAlerts: filtra alertas de `confirmation_pending`
 *     para usuários sem permissão de agenda.
 *
 * Sem 403 — recursos sem permissão são silenciosamente omitidos do
 * payload (defense in depth dentro do response).
 *
 * @see specs/010-dashboard-home/research.md R8
 */
final class PanelHomePolicy
{
    public function canSeeClinicScope(User $user): bool
    {
        return $user->hasRole('admin-clinica');
    }

    public function canSeeWebhookDlqAlerts(User $user): bool
    {
        return $user->can('webhook.manage');
    }

    public function canSeeConfirmationAlerts(User $user): bool
    {
        return $user->can('agenda.view');
    }
}
