<?php

namespace App\Jobs\Agenda;

use App\Models\Agenda\CalendarSyncAccount;
use App\Services\Agenda\Calendar\GoogleCalendarPollFallbackService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * T153 — Recebe push notification do Google Watch channel (R3).
 *
 * NÃO usa TenantAwareJob — pushing chega sem contexto. Resolve tenant via
 * watch_channel_id → CalendarSyncAccount.tenant_id.
 *
 * Reusa GoogleCalendarPollFallbackService::pollAccount() para sincronizar
 * mudanças (push notification não traz payload do evento — apenas trigger).
 */
final class ProcessGoogleCalendarPushJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $channelId) {}

    public function handle(GoogleCalendarPollFallbackService $service): void
    {
        $account = CalendarSyncAccount::query()
            ->withoutTenantScope()
            ->where('watch_channel_id', $this->channelId)
            ->where('status', 'connected')
            ->first();

        if (! $account) {
            return;
        }

        // Re-resolve tenant context para BelongsToTenant scope
        app()->instance('tenant', $account->tenant);

        $service->pollAccount($account);
    }
}
