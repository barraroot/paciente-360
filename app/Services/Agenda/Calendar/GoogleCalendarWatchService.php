<?php

namespace App\Services\Agenda\Calendar;

use App\Models\Agenda\CalendarSyncAccount;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * T150 — Watch channels Google Calendar (R3 — push notifications).
 *
 * `channel.token` = HMAC-SHA256 de `{tenant_id}.{prof_id}.{channel_id}` com
 * APP_KEY — validado em ValidateGoogleChannelToken middleware.
 */
final class GoogleCalendarWatchService
{
    public function __construct(private readonly GoogleCalendarApiClient $client) {}

    public function subscribe(CalendarSyncAccount $account): void
    {
        if ($account->google_calendar_id === null) {
            Log::warning('agenda.google.watch.skip_no_calendar', ['account_id' => $account->id]);

            return;
        }

        $channelId = $account->watch_channel_id ?? (string) Str::uuid();
        $token = $this->buildHmacToken($account, $channelId);
        $webhookBase = (string) config('services.google_calendar.webhook_base_url', '');

        $result = $this->client->watchChannel(
            $account,
            $account->google_calendar_id,
            "{$webhookBase}/{$channelId}",
            $token,
        );

        $account->update([
            'watch_channel_id' => $result['id'] ?? $channelId,
            'watch_channel_resource_id' => $result['resourceId'],
            'watch_channel_expires_at' => isset($result['expiration'])
                ? now()->setTimestamp(intdiv($result['expiration'], 1000))
                : null,
        ]);
    }

    public function unsubscribe(CalendarSyncAccount $account): void
    {
        if (! $account->watch_channel_id || ! $account->watch_channel_resource_id) {
            return;
        }

        try {
            $this->client->stopWatchChannel($account->watch_channel_id, $account->watch_channel_resource_id);
        } catch (\Throwable $e) {
            Log::info('agenda.google.watch.stop_failed_ok', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }

        $account->update([
            'watch_channel_id' => null,
            'watch_channel_resource_id' => null,
            'watch_channel_expires_at' => null,
        ]);
    }

    /**
     * Renova channels que expiram nas próximas N horas (default 48h via env).
     *
     * @return int número de canais renovados
     */
    public function renewIfNearExpiry(): int
    {
        $hoursAhead = (int) config('services.google_calendar.watch_channel_renew_hours', 48);

        $accounts = CalendarSyncAccount::query()
            ->withoutTenantScope()
            ->needsWatchRenewal($hoursAhead)
            ->get();

        $count = 0;
        foreach ($accounts as $account) {
            $this->unsubscribe($account);
            $this->subscribe($account);
            $count++;
        }

        return $count;
    }

    public function buildHmacToken(CalendarSyncAccount $account, string $channelId): string
    {
        $payload = "{$account->tenant_id}.{$account->professional_id}.{$channelId}";

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }
}
