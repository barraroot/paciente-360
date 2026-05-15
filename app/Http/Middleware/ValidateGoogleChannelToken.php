<?php

namespace App\Http\Middleware;

use App\Models\Agenda\CalendarSyncAccount;
use App\Services\Agenda\Calendar\GoogleCalendarWatchService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * T162 — Valida HMAC do header X-Goog-Channel-Token (R3).
 *
 * Sempre retorna HTTP 200 mesmo em falha (evita retry storms do Google).
 * Erros logados ao Sentry.
 */
class ValidateGoogleChannelToken
{
    public function __construct(private readonly GoogleCalendarWatchService $watchService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $channelId = $request->route('channelId') ?? $request->header('X-Goog-Channel-Id');
        $token = (string) $request->header('X-Goog-Channel-Token', '');

        if (! $channelId || ! $token) {
            Log::warning('agenda.google.webhook.missing_headers', [
                'channel_id' => $channelId,
                'has_token' => $token !== '',
            ]);

            return response()->json(['ok' => true], 200);
        }

        $account = CalendarSyncAccount::query()
            ->withoutTenantScope()
            ->where('watch_channel_id', $channelId)
            ->first();

        if (! $account) {
            Log::warning('agenda.google.webhook.unknown_channel', ['channel_id' => $channelId]);

            return response()->json(['ok' => true], 200);
        }

        $expected = $this->watchService->buildHmacToken($account, $channelId);

        if (! hash_equals($expected, $token)) {
            Log::warning('agenda.google.webhook.invalid_hmac', [
                'channel_id' => $channelId,
                'account_id' => $account->id,
            ]);

            return response()->json(['ok' => true], 200);
        }

        $request->attributes->set('agenda.calendar_sync_account', $account);

        return $next($request);
    }
}
