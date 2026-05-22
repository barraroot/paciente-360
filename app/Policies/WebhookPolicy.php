<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Models\User;

/**
 * **T201 (Fase 8 — Lote D US-11.1)** — Policy de webhooks.
 *
 * Abilities:
 *   - `webhook.manage` — Admin Clínica (CRUD endpoints).
 *   - `webhook.view_deliveries` — Admin Clínica + suporte.
 *   - `webhook.resend_dlq` — Admin Clínica apenas.
 */
class WebhookPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('webhook.manage') || $user->can('webhook.view_deliveries');
    }

    public function view(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->viewAny($user) && $endpoint->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('webhook.manage');
    }

    public function update(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->can('webhook.manage') && $endpoint->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->update($user, $endpoint);
    }

    public function resendDlq(User $user): bool
    {
        return $user->can('webhook.resend_dlq') || $user->can('webhook.manage');
    }
}
