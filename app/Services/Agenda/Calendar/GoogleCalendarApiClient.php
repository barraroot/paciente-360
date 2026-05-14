<?php

namespace App\Services\Agenda\Calendar;

use App\Models\Agenda\CalendarSyncAccount;
use Illuminate\Support\Str;

/**
 * Wrapper fino sobre `Google\Client` (R1).
 *
 * Existe primariamente para facilitar mocking em testes — em vez de instanciar
 * Google\Client direto, services consomem este wrapper. Em testes, registra
 * fake instance no container.
 *
 * Implementação real depende do pacote `google/apiclient` que está em
 * composer.json. As chamadas reais à Google API ficam aqui isoladas para que
 * o resto do código seja testável sem rede.
 */
class GoogleCalendarApiClient
{
    /**
     * @return array{authorize_url:string, state:string}
     */
    public function buildAuthorizeUrl(string $statePayload): array
    {
        // TODO: implementação real com Google\Client::createAuthUrl()
        // Por enquanto retorna stub determinístico para testes.
        $state = base64_encode($statePayload);

        return [
            'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth?stub_state='.$state,
            'state' => $state,
        ];
    }

    /**
     * @return array{access_token:string, refresh_token:?string, expires_in:int, sub:string, email:string}
     */
    public function exchangeCodeForToken(string $code): array
    {
        // TODO: Google\Client::fetchAccessTokenWithAuthCode($code)
        return [
            'access_token' => 'stub_access_'.bin2hex(random_bytes(8)),
            'refresh_token' => 'stub_refresh_'.bin2hex(random_bytes(8)),
            'expires_in' => 3600,
            'sub' => 'stub_user_'.bin2hex(random_bytes(4)),
            'email' => 'stub@example.com',
        ];
    }

    /**
     * @return array{access_token:string, expires_in:int}
     */
    public function refreshToken(string $refreshToken): array
    {
        // TODO: Google\Client::fetchAccessTokenWithRefreshToken($refreshToken)
        return [
            'access_token' => 'stub_refreshed_'.bin2hex(random_bytes(8)),
            'expires_in' => 3600,
        ];
    }

    public function revokeToken(string $accessToken): bool
    {
        // TODO: Google\Client::revokeToken($accessToken)
        return true;
    }

    /**
     * Cria sub-calendário dedicado (clarify nº 15).
     *
     * @return array{id:string, summary:string}
     */
    public function createSubCalendar(CalendarSyncAccount $account, string $summary): array
    {
        // TODO: $service->calendars->insert(new \Google\Service\Calendar\Calendar([...]))
        return [
            'id' => 'cal_'.bin2hex(random_bytes(10)).'@group.calendar.google.com',
            'summary' => $summary,
        ];
    }

    /**
     * @param array{summary:string, description:string, start:array, end:array} $eventBody
     * @return array{id:string, etag:string}
     */
    public function insertEvent(CalendarSyncAccount $account, string $calendarId, array $eventBody): array
    {
        // TODO: $service->events->insert($calendarId, new Google\Service\Calendar\Event($eventBody))
        return [
            'id' => 'evt_'.bin2hex(random_bytes(10)),
            'etag' => '"'.bin2hex(random_bytes(8)).'"',
        ];
    }

    public function patchEvent(CalendarSyncAccount $account, string $calendarId, string $eventId, array $eventBody): array
    {
        // TODO: $service->events->patch($calendarId, $eventId, ...)
        return [
            'id' => $eventId,
            'etag' => '"'.bin2hex(random_bytes(8)).'"',
        ];
    }

    public function deleteEvent(CalendarSyncAccount $account, string $calendarId, string $eventId): void
    {
        // TODO: $service->events->delete($calendarId, $eventId)
    }

    /**
     * Polling fallback — lista eventos atualizados desde lastPolledAt.
     *
     * @return list<array{id:string, summary:string, start:array, end:array, status:string}>
     */
    public function listEventsUpdatedSince(CalendarSyncAccount $account, string $calendarId, ?\DateTimeInterface $since = null): array
    {
        // TODO: $service->events->listEvents($calendarId, ['updatedMin' => $since?->format(\DateTime::RFC3339)])
        return [];
    }

    /**
     * Registra watch channel (push notifications via webhook).
     *
     * @return array{id:string, resourceId:string, expiration:int} expiration em ms unix
     */
    public function watchChannel(CalendarSyncAccount $account, string $calendarId, string $webhookUrl, string $token): array
    {
        // TODO: $service->events->watch($calendarId, new \Google\Service\Calendar\Channel([...]))
        return [
            'id' => $account->watch_channel_id ?? (string) Str::uuid(),
            'resourceId' => 'rsrc_'.bin2hex(random_bytes(10)),
            'expiration' => (int) ((time() + 7 * 86400) * 1000), // +7 dias
        ];
    }

    public function stopWatchChannel(string $channelId, string $resourceId): void
    {
        // TODO: $service->channels->stop(new \Google\Service\Calendar\Channel(['id' => $channelId, 'resourceId' => $resourceId]))
    }
}
