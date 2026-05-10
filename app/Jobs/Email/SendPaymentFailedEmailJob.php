<?php

namespace App\Jobs\Email;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job de envio do e-mail de falha de cobrança (T189).
 *
 * Não estende `TenantAwareJob` pois o `tenantId` é passado explicitamente
 * (contexto não está disponível no webhook handler).
 */
final class SendPaymentFailedEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $userId,
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::query()->withoutGlobalScopes()->findOrFail($this->tenantId);
        $user = User::query()->withoutGlobalScopes()->findOrFail($this->userId);

        $user->notify(new PaymentFailedNotification($tenant));
    }
}
