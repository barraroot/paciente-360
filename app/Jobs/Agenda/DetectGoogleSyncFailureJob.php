<?php

namespace App\Jobs\Agenda;

use App\Events\Agenda\CalendarioExternoSincronizado;
use App\Models\Agenda\CalendarSyncAccount;
use App\Services\Agenda\Calendar\GoogleCalendarOAuthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * T156 — Detector de sync failure (R5 — UX revogação OAuth).
 *
 * Wrapper invocado em qualquer chamada Google API que retornou 401/invalid_grant.
 * Estratégia:
 *  1. Tenta refresh automático.
 *  2. Se falha 2x em window 1min → marca disconnected + dispatch notification.
 */
final class DetectGoogleSyncFailureJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $accountId) {}

    public function handle(GoogleCalendarOAuthService $oauth): void
    {
        $account = CalendarSyncAccount::query()->withoutTenantScope()->find($this->accountId);
        if (! $account || $account->status === 'disconnected') {
            return;
        }

        // Try refresh first (R5)
        if ($oauth->tryRefresh($account)) {
            return;
        }

        // 2x failure in 1min → declare disconnected
        $key = "agenda:google:auth_failures:{$account->id}";
        $count = (int) Cache::get($key, 0);
        Cache::put($key, $count + 1, 60);

        if ($count + 1 >= 2) {
            $account->update([
                'status' => 'disconnected',
                'last_disconnect_at' => now(),
            ]);
            CalendarioExternoSincronizado::dispatch($account->fresh(), 'disconnected');
            Cache::forget($key);
        }
    }
}
