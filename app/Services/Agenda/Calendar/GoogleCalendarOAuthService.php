<?php

namespace App\Services\Agenda\Calendar;

use App\Events\Agenda\CalendarioExternoSincronizado;
use App\Models\Agenda\CalendarSyncAccount;
use App\Models\Professional;
use Illuminate\Support\Facades\Log;

/**
 * T147 — OAuth flow Google Calendar (R5 — UX revogação OAuth resolvido).
 */
final class GoogleCalendarOAuthService
{
    public function __construct(
        private readonly GoogleCalendarApiClient $client,
        private readonly GoogleSubCalendarManager $subCal,
    ) {}

    /**
     * @return array{authorize_url:string, state:string}
     */
    public function buildAuthorizeUrl(Professional $professional): array
    {
        $statePayload = json_encode([
            'tenant_id' => $professional->tenant_id,
            'professional_id' => $professional->id,
            'csrf' => bin2hex(random_bytes(16)),
        ], JSON_THROW_ON_ERROR);

        return $this->client->buildAuthorizeUrl($statePayload);
    }

    /**
     * Callback handler: troca code por token, cria sub-calendário, persiste account.
     *
     * @throws \DomainException calendar_subcalendar_creation_failed | invalid_state
     */
    public function handleCallback(Professional $professional, string $code, string $state): CalendarSyncAccount
    {
        // Validação básica de state (em prod: validar via cache/session)
        $decoded = json_decode(base64_decode($state), true);
        if (! is_array($decoded) || ! isset($decoded['tenant_id'])
            || (int) $decoded['tenant_id'] !== (int) $professional->tenant_id
            || (int) $decoded['professional_id'] !== (int) $professional->id) {
            throw new \DomainException('invalid_state');
        }

        $token = $this->client->exchangeCodeForToken($code);

        // Encontra ou cria account (UNIQUE tenant_id + professional_id — clarify nº 15)
        $account = CalendarSyncAccount::query()
            ->where('tenant_id', $professional->tenant_id)
            ->where('professional_id', $professional->id)
            ->first();

        $isReconnection = $account !== null;

        if ($account === null) {
            $account = new CalendarSyncAccount;
            $account->tenant_id = $professional->tenant_id;
            $account->professional_id = $professional->id;
            $account->provider = 'google';
        }

        $account->fill([
            'provider_user_id' => $token['sub'],
            'provider_email' => $token['email'],
            'encrypted_access_token' => $token['access_token'],
            'encrypted_refresh_token' => $token['refresh_token'] ?? $account->encrypted_refresh_token,
            'expires_at' => now()->addSeconds($token['expires_in']),
            'status' => 'connected',
        ]);

        if ($isReconnection) {
            $account->last_reconnect_at = now();
        }

        $account->save();

        // Cria sub-calendário (ou reusa se existe — R5)
        if (! $isReconnection || $account->google_calendar_id === null) {
            $this->subCal->createSubCalendar($account, $professional->tenant);
        }

        CalendarioExternoSincronizado::dispatch($account->fresh(), 'connected');

        return $account->fresh();
    }

    /**
     * R5 — tenta refresh antes de declarar falha.
     *
     * @return bool true se refresh bem sucedido
     */
    public function tryRefresh(CalendarSyncAccount $account): bool
    {
        if ($account->encrypted_refresh_token === null) {
            return false;
        }

        try {
            $refreshed = $this->client->refreshToken($account->encrypted_refresh_token);

            $account->update([
                'encrypted_access_token' => $refreshed['access_token'],
                'expires_at' => now()->addSeconds($refreshed['expires_in']),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('agenda.google.refresh_failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function disconnect(CalendarSyncAccount $account): void
    {
        // Tenta revogar token + parar watch channel — silently fail OK
        try {
            $this->client->revokeToken($account->encrypted_access_token);
        } catch (\Throwable $e) {
            Log::info('agenda.google.revoke_failed_ok', ['account_id' => $account->id]);
        }

        if ($account->watch_channel_id && $account->watch_channel_resource_id) {
            try {
                $this->client->stopWatchChannel($account->watch_channel_id, $account->watch_channel_resource_id);
            } catch (\Throwable $e) {
                // OK — pode já estar expirado/inativo
            }
        }

        $account->update([
            'status' => 'disconnected',
            'last_disconnect_at' => now(),
            'watch_channel_id' => null,
            'watch_channel_resource_id' => null,
            'watch_channel_expires_at' => null,
        ]);

        CalendarioExternoSincronizado::dispatch($account->fresh(), 'disconnected');
    }
}
