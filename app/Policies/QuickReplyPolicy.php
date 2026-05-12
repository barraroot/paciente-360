<?php

namespace App\Policies;

use App\Domain\Messaging\QuickReply\Models\QuickReply;
use App\Models\User;

/**
 * T239 — Policy para QuickReply.
 *
 * Regras de autorização:
 *  - view: escopo tenant OU owner do usuário (desde que same tenant)
 *  - update/delete: escopo tenant com `quick_reply.manage` OU owner
 *  - render: qualquer user com `inbox.respond` no mesmo tenant
 */
final class QuickReplyPolicy
{
    public function view(User $user, QuickReply $reply): bool
    {
        if ($reply->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Tenant scope — todos com inbox.respond podem ver
        if ($reply->owner_user_id === null) {
            return $user->can('inbox.respond');
        }

        // Privada — somente o dono
        return $reply->owner_user_id === $user->id;
    }

    public function update(User $user, QuickReply $reply): bool
    {
        if ($reply->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Tenant scope — exige quick_reply.manage
        if ($reply->owner_user_id === null) {
            return $user->can('quick_reply.manage');
        }

        // Privada — somente o dono
        return $reply->owner_user_id === $user->id;
    }

    public function delete(User $user, QuickReply $reply): bool
    {
        return $this->update($user, $reply);
    }

    public function render(User $user, QuickReply $reply): bool
    {
        return $this->view($user, $reply);
    }
}
