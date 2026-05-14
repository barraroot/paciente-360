<?php

namespace Tests\Fakes\Agenda;

use App\Models\Agenda\CalendarSyncAccount;
use App\Services\Agenda\Calendar\GoogleCalendarApiClient;
use Illuminate\Support\Str;

/**
 * Fake do GoogleCalendarApiClient para tests sem rede.
 *
 * Substitui chamadas Google API reais por valores determinísticos.
 * Registrar no setUp via:
 *
 *   $this->app->instance(GoogleCalendarApiClient::class, new FakeGoogleCalendarApiClient());
 */
class FakeGoogleCalendarApiClient extends GoogleCalendarApiClient
{
    /** @var list<array{method:string, args:array}> */
    public array $calls = [];

    public function buildAuthorizeUrl(string $statePayload): array
    {
        $this->record(__FUNCTION__, ['statePayload' => $statePayload]);
        $state = base64_encode($statePayload);

        return [
            'authorize_url' => "https://accounts.google.com/o/oauth2/v2/auth?fake_state={$state}",
            'state' => $state,
        ];
    }

    public function exchangeCodeForToken(string $code): array
    {
        $this->record(__FUNCTION__, compact('code'));

        return [
            'access_token' => 'fake_access_'.bin2hex(random_bytes(8)),
            'refresh_token' => 'fake_refresh_'.bin2hex(random_bytes(8)),
            'expires_in' => 3600,
            'sub' => 'fake_sub_'.bin2hex(random_bytes(4)),
            'email' => 'fake@example.com',
        ];
    }

    public function refreshToken(string $refreshToken): array
    {
        $this->record(__FUNCTION__, compact('refreshToken'));

        return [
            'access_token' => 'fake_refreshed_'.bin2hex(random_bytes(8)),
            'expires_in' => 3600,
        ];
    }

    public function revokeToken(string $accessToken): bool
    {
        $this->record(__FUNCTION__, ['accessToken' => substr($accessToken, 0, 8).'...']);

        return true;
    }

    public function createSubCalendar(CalendarSyncAccount $account, string $summary): array
    {
        $this->record(__FUNCTION__, ['account_id' => $account->id, 'summary' => $summary]);

        return [
            'id' => 'fake_cal_'.bin2hex(random_bytes(8)).'@group.calendar.google.com',
            'summary' => $summary,
        ];
    }

    public function insertEvent(CalendarSyncAccount $account, string $calendarId, array $eventBody): array
    {
        $this->record(__FUNCTION__, compact('calendarId', 'eventBody'));

        return [
            'id' => 'fake_evt_'.bin2hex(random_bytes(8)),
            'etag' => '"fake_etag_'.bin2hex(random_bytes(4)).'"',
        ];
    }

    public function patchEvent(CalendarSyncAccount $account, string $calendarId, string $eventId, array $eventBody): array
    {
        $this->record(__FUNCTION__, compact('calendarId', 'eventId', 'eventBody'));

        return [
            'id' => $eventId,
            'etag' => '"fake_etag_'.bin2hex(random_bytes(4)).'"',
        ];
    }

    public function deleteEvent(CalendarSyncAccount $account, string $calendarId, string $eventId): void
    {
        $this->record(__FUNCTION__, compact('calendarId', 'eventId'));
    }

    public function listEventsUpdatedSince(CalendarSyncAccount $account, string $calendarId, ?\DateTimeInterface $since = null): array
    {
        $this->record(__FUNCTION__, ['calendarId' => $calendarId, 'since' => $since?->format('c')]);

        return [];
    }

    public function watchChannel(CalendarSyncAccount $account, string $calendarId, string $webhookUrl, string $token): array
    {
        $this->record(__FUNCTION__, compact('calendarId', 'webhookUrl'));

        return [
            'id' => $account->watch_channel_id ?? (string) Str::uuid(),
            'resourceId' => 'fake_rsrc_'.bin2hex(random_bytes(8)),
            'expiration' => (int) ((time() + 7 * 86400) * 1000),
        ];
    }

    public function stopWatchChannel(string $channelId, string $resourceId): void
    {
        $this->record(__FUNCTION__, compact('channelId', 'resourceId'));
    }

    /** @param array<string, mixed> $args */
    private function record(string $method, array $args): void
    {
        $this->calls[] = compact('method', 'args');
    }
}
