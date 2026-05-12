<?php

namespace App\Policies;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Models\User;

/**
 * T076 — Policy de autorização para `Channel`.
 *
 * Mapeia abilities do Spatie (`channel.connect`, `channel.disconnect`,
 * `inbox.view`) para as actions da policy.
 *
 * O isolamento por tenant é garantido upstream pelo `TenantScope` global
 * no Model Channel. A policy apenas verifica as abilities.
 */
final class ChannelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inbox.view');
    }

    public function view(User $user, Channel $channel): bool
    {
        return $user->can('inbox.view');
    }

    public function create(User $user): bool
    {
        return $user->can('channel.connect');
    }

    public function update(User $user, Channel $channel): bool
    {
        return $user->can('channel.connect');
    }

    public function delete(User $user, Channel $channel): bool
    {
        return $user->can('channel.disconnect');
    }

    public function reconnect(User $user, Channel $channel): bool
    {
        return $user->can('channel.connect');
    }
}
