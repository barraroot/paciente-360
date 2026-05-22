<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Campaigns\Models\Campaign;
use App\Models\User;

/**
 * **T168 (Fase 8 — Lote C US-9.1)** — Policy do domínio Campaign.
 *
 * Abilities Spatie (Phase 1 T003):
 *   - `campaign.create`   — criar/editar/cancelar
 *   - `campaign.dispatch` — disparar (requer ambos: create + dispatch)
 */
class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('campaign.create') || $user->can('report.view');
    }

    public function view(User $user, Campaign $campaign): bool
    {
        if (! $user->can('campaign.create') && ! $user->can('report.view')) {
            return false;
        }

        return $user->tenant_id === null || $campaign->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('campaign.create');
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->can('campaign.create')
            && in_array($campaign->status->value, ['draft', 'scheduled'], true)
            && ($user->tenant_id === null || $campaign->tenant_id === $user->tenant_id);
    }

    public function cancel(User $user, Campaign $campaign): bool
    {
        return $user->can('campaign.create')
            && ! $campaign->status->isTerminal()
            && ($user->tenant_id === null || $campaign->tenant_id === $user->tenant_id);
    }

    public function dispatch(User $user, Campaign $campaign): bool
    {
        return $user->can('campaign.dispatch')
            && in_array($campaign->status->value, ['draft', 'scheduled'], true)
            && ($user->tenant_id === null || $campaign->tenant_id === $user->tenant_id);
    }
}
