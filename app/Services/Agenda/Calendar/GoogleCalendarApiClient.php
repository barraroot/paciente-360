<?php

namespace App\Services\Agenda\Calendar;

use App\Models\Agenda\CalendarSyncAccount;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendarService;
use Google\Service\Calendar\Calendar as GoogleCalendarResource;
use Google\Service\Calendar\Channel as GoogleChannel;
use Google\Service\Calendar\Event as GoogleEvent;
use Google\Service\Calendar\EventDateTime as GoogleEventDateTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Wrapper sobre `Google\Client` (R1).
 *
 * Implementação real via `google/apiclient ^2.18` (instalado em Lote A T001).
 *
 * Para testes, registrar fake no container:
 *
 *   $this->app->instance(GoogleCalendarApiClient::class, new FakeGoogleCalendarApiClient());
 *
 * Métodos sem `CalendarSyncAccount` (buildAuthorizeUrl/exchangeCodeForToken) não precisam
 * de token autenticado; demais métodos hidratam um `Google\Client` per-request com o
 * encrypted_access_token decryptado.
 */
class GoogleCalendarApiClient
{
    private const SCOPES = [
        GoogleCalendarService::CALENDAR,           // criar sub-calendário (clarify nº 15)
        GoogleCalendarService::CALENDAR_EVENTS,    // CRUD de eventos no sub-cal
        'openid',
        'email',
        'profile',
    ];

    /**
     * @return array{authorize_url:string, state:string}
     */
    public function buildAuthorizeUrl(string $statePayload): array
    {
        $client = $this->makeBaseClient();
        $client->setScopes(self::SCOPES);
        $client->setAccessType('offline');           // garante refresh_token
        $client->setPrompt('consent');               // força consent (refresh_token novo)
        $client->setIncludeGrantedScopes(true);

        $state = base64_encode($statePayload);
        $client->setState($state);

        return [
            'authorize_url' => $client->createAuthUrl(),
            'state' => $state,
        ];
    }

    /**
     * @return array{access_token:string, refresh_token:?string, expires_in:int, sub:string, email:string}
     */
    public function exchangeCodeForToken(string $code): array
    {
        $client = $this->makeBaseClient();
        $client->setScopes(self::SCOPES);

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \RuntimeException('google_oauth_exchange_failed: '.($token['error'] ?? 'unknown'));
        }

        // Extrai sub/email do id_token (JWT) — payload do segmento meio
        $idTokenParts = explode('.', (string) ($token['id_token'] ?? ''));
        $idPayload = count($idTokenParts) >= 2
            ? (array) json_decode(base64_decode(strtr($idTokenParts[1], '-_', '+/')), true)
            : [];

        return [
            'access_token' => (string) $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
            'expires_in' => (int) ($token['expires_in'] ?? 3600),
            'sub' => (string) ($idPayload['sub'] ?? ''),
            'email' => (string) ($idPayload['email'] ?? ''),
        ];
    }

    /**
     * @return array{access_token:string, expires_in:int}
     */
    public function refreshToken(string $refreshToken): array
    {
        $client = $this->makeBaseClient();
        $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);

        if (isset($token['error'])) {
            throw new \RuntimeException('google_oauth_refresh_failed: '.($token['error'] ?? 'unknown'));
        }

        return [
            'access_token' => (string) $token['access_token'],
            'expires_in' => (int) ($token['expires_in'] ?? 3600),
        ];
    }

    public function revokeToken(string $accessToken): bool
    {
        $client = $this->makeBaseClient();
        try {
            return (bool) $client->revokeToken($accessToken);
        } catch (\Throwable $e) {
            Log::info('agenda.google.revoke_failed_swallowed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Cria sub-calendário dedicado (clarify nº 15).
     *
     * @return array{id:string, summary:string}
     */
    public function createSubCalendar(CalendarSyncAccount $account, string $summary): array
    {
        $service = $this->serviceFor($account);

        $resource = new GoogleCalendarResource;
        $resource->setSummary($summary);
        $resource->setTimeZone((string) ($account->tenant->timezone ?: 'America/Sao_Paulo'));

        $created = $service->calendars->insert($resource);

        return [
            'id' => (string) $created->getId(),
            'summary' => (string) $created->getSummary(),
        ];
    }

    /**
     * @param array{summary:string, description:string, start:array, end:array} $eventBody
     * @return array{id:string, etag:string}
     */
    public function insertEvent(CalendarSyncAccount $account, string $calendarId, array $eventBody): array
    {
        $service = $this->serviceFor($account);
        $event = $this->buildGoogleEvent($eventBody);

        $created = $service->events->insert($calendarId, $event);

        return [
            'id' => (string) $created->getId(),
            'etag' => (string) $created->getEtag(),
        ];
    }

    /**
     * @param array{summary:string, description:string, start:array, end:array} $eventBody
     * @return array{id:string, etag:string}
     */
    public function patchEvent(CalendarSyncAccount $account, string $calendarId, string $eventId, array $eventBody): array
    {
        $service = $this->serviceFor($account);
        $event = $this->buildGoogleEvent($eventBody);

        $patched = $service->events->patch($calendarId, $eventId, $event);

        return [
            'id' => (string) $patched->getId(),
            'etag' => (string) $patched->getEtag(),
        ];
    }

    public function deleteEvent(CalendarSyncAccount $account, string $calendarId, string $eventId): void
    {
        $service = $this->serviceFor($account);
        $service->events->delete($calendarId, $eventId);
    }

    /**
     * Polling fallback — lista eventos atualizados desde lastPolledAt.
     *
     * @return list<array{id:string, summary:string, start:array, end:array, status:string}>
     */
    public function listEventsUpdatedSince(CalendarSyncAccount $account, string $calendarId, ?\DateTimeInterface $since = null): array
    {
        $service = $this->serviceFor($account);
        $params = [
            'singleEvents' => true,
            'orderBy' => 'updated',
            'maxResults' => 250,
        ];

        if ($since) {
            $params['updatedMin'] = Carbon::instance($since)->toRfc3339String();
        }

        $events = $service->events->listEvents($calendarId, $params);
        $items = [];

        foreach ($events->getItems() as $event) {
            /** @var GoogleEvent $event */
            $start = $event->getStart();
            $end = $event->getEnd();

            $items[] = [
                'id' => (string) $event->getId(),
                'summary' => (string) ($event->getSummary() ?? ''),
                'start' => [
                    'dateTime' => $start?->getDateTime() ?? $start?->getDate(),
                    'date' => $start?->getDate(),
                ],
                'end' => [
                    'dateTime' => $end?->getDateTime() ?? $end?->getDate(),
                    'date' => $end?->getDate(),
                ],
                'status' => (string) $event->getStatus(),
            ];
        }

        return $items;
    }

    /**
     * Registra watch channel (push notifications via webhook).
     *
     * @return array{id:string, resourceId:string, expiration:int} expiration em ms unix
     */
    public function watchChannel(CalendarSyncAccount $account, string $calendarId, string $webhookUrl, string $token): array
    {
        $service = $this->serviceFor($account);

        $channel = new GoogleChannel;
        $channel->setId($account->watch_channel_id ?? (string) Str::uuid());
        $channel->setType('web_hook');
        $channel->setAddress($webhookUrl);
        $channel->setToken($token);

        $created = $service->events->watch($calendarId, $channel);

        return [
            'id' => (string) $created->getId(),
            'resourceId' => (string) $created->getResourceId(),
            'expiration' => (int) ($created->getExpiration() ?? ((time() + 7 * 86400) * 1000)),
        ];
    }

    public function stopWatchChannel(string $channelId, string $resourceId): void
    {
        // channels.stop não exige autenticação per-tenant — qualquer client autorizado.
        $client = $this->makeBaseClient();
        $service = new GoogleCalendarService($client);

        $channel = new GoogleChannel;
        $channel->setId($channelId);
        $channel->setResourceId($resourceId);

        $service->channels->stop($channel);
    }

    // ─── helpers internos ─────────────────────────────────────────────

    private function makeBaseClient(): GoogleClient
    {
        $client = new GoogleClient;
        $client->setApplicationName('Paciente360');
        $client->setClientId((string) config('services.google_calendar.client_id'));
        $client->setClientSecret((string) config('services.google_calendar.client_secret'));
        $client->setRedirectUri((string) config('services.google_calendar.redirect_uri'));

        return $client;
    }

    /**
     * Constrói o `Google\Service\Calendar` autenticado com o access_token
     * (decryptado pelo cast Eloquent) do account.
     */
    private function serviceFor(CalendarSyncAccount $account): GoogleCalendarService
    {
        $client = $this->makeBaseClient();
        $client->setScopes(self::SCOPES);

        // encrypted_access_token tem cast `encrypted` → ao acessar o atributo,
        // o cast aplica Crypt::decryptString automaticamente.
        $tokenArray = [
            'access_token' => $account->encrypted_access_token,
            'refresh_token' => $account->encrypted_refresh_token,
            'expires_in' => (int) max(0, $account->expires_at?->diffInSeconds(now(), absolute: true) ?? 0),
            'created' => (int) ($account->expires_at?->copy()->subSeconds(3600)->getTimestamp() ?? time() - 3600),
        ];
        $client->setAccessToken($tokenArray);

        // Auto-refresh se expirado (R5 — defesa em profundidade; geralmente
        // OAuthService::tryRefresh já lidou)
        if ($client->isAccessTokenExpired() && $account->encrypted_refresh_token) {
            try {
                $newToken = $client->fetchAccessTokenWithRefreshToken($account->encrypted_refresh_token);
                if (! isset($newToken['error'])) {
                    $account->update([
                        'encrypted_access_token' => $newToken['access_token'],
                        'expires_at' => now()->addSeconds((int) ($newToken['expires_in'] ?? 3600)),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('agenda.google.auto_refresh_failed', [
                    'account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return new GoogleCalendarService($client);
    }

    /**
     * @param array{summary:string, description:string, start:array, end:array} $body
     */
    private function buildGoogleEvent(array $body): GoogleEvent
    {
        $event = new GoogleEvent;
        $event->setSummary($body['summary']);
        $event->setDescription($body['description'] ?? '');

        $start = new GoogleEventDateTime;
        $start->setDateTime($body['start']['dateTime']);
        if (! empty($body['start']['timeZone'])) {
            $start->setTimeZone($body['start']['timeZone']);
        }
        $event->setStart($start);

        $end = new GoogleEventDateTime;
        $end->setDateTime($body['end']['dateTime']);
        if (! empty($body['end']['timeZone'])) {
            $end->setTimeZone($body['end']['timeZone']);
        }
        $event->setEnd($end);

        return $event;
    }
}
