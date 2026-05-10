<?php

namespace App\Jobs\Email;

use App\Jobs\TenantAwareJob;
use App\Models\User;
use App\Notifications\WelcomeNotification;

/**
 * Job de envio do e-mail de boas-vindas ao Admin Clínica (US-1.1 — T145).
 *
 * Estende `TenantAwareJob` para que o tenant seja rehidratado no worker
 * antes do envio (Princípio II — isolamento por tenant).
 *
 * @see App\Notifications\WelcomeNotification
 * @see App\Services\Tenant\TenantRegistrationService
 */
final class SendWelcomeEmailJob extends TenantAwareJob
{
    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $userId,
    ) {
        parent::__construct();
    }

    protected function run(): void
    {
        $user = User::query()
            ->withoutGlobalScopes()
            ->findOrFail($this->userId);

        $tenant = app('tenant');

        $user->notify(new WelcomeNotification($tenant));
    }
}
