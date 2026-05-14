<?php

namespace App\Policies\Agenda;

use App\Models\Agenda\CalendarSyncAccount;
use App\Models\User;

/**
 * T166 — CalendarSyncAccountPolicy (US-6.7).
 */
class CalendarSyncAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('calendar_sync.configure');
    }

    public function view(User $user, CalendarSyncAccount $account): bool
    {
        return $user->tenant_id === $account->tenant_id && $user->can('calendar_sync.configure');
    }

    public function manage(User $user, CalendarSyncAccount $account): bool
    {
        return $user->tenant_id === $account->tenant_id && $user->can('calendar_sync.configure');
    }
}
