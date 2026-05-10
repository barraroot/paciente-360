<?php

namespace App\Jobs\Email;

use App\Jobs\TenantAwareJob;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Job de envio do e-mail de recuperação de senha (US-2.3 — FR-030).
 *
 * Estende `TenantAwareJob` para garantir que o contexto do tenant seja
 * rehidratado no worker antes do envio (Princípio II — isolamento por tenant).
 *
 * O token em claro trafega apenas neste job e no e-mail enviado ao usuário —
 * nunca é persistido no banco em claro.
 *
 * @see App\Services\Auth\PasswordResetService::requestReset()
 * @see App\Mail\ResetPasswordMail
 */
final class SendPasswordResetEmailJob extends TenantAwareJob
{
    /** Número de tentativas antes de desistir. */
    public int $tries = 3;

    /** Backoff em segundos entre tentativas. */
    public int $backoff = 30;

    public function __construct(
        public readonly int $userId,
        public readonly string $tokenInClaro,
    ) {
        parent::__construct();
    }

    protected function run(): void
    {
        $user = User::query()
            ->withoutGlobalScopes()
            ->findOrFail($this->userId);

        $tenant = app('tenant');

        Mail::to($user)->send(new ResetPasswordMail($user, $tenant, $this->tokenInClaro));
    }
}
