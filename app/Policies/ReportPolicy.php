<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * **T257 (Fase 8 — Lote E US-10.1)** — Policy de relatórios.
 *
 * Abilities:
 *   - `report.view` — Admin Clínica + Médico (escopo restrito via Service Q13).
 *   - `report.export` — Admin Clínica apenas (audit reinforçado).
 */
class ReportPolicy
{
    public function view(User $user): bool
    {
        return $user->can('report.view');
    }

    public function export(User $user): bool
    {
        return $user->can('report.export');
    }
}
