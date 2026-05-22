<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * **T220 (Fase 8 — Lote D US-11.2)** — OAuth 2.0 authenticator (Q18 gated).
 *
 * Aceita Bearer token JWT-like emitido pelo `OauthClientService`. Decodifica
 * o payload base64 e injeta `tenant_id` no request.
 *
 * Quando `oauth_enabled = false`, o middleware passa adiante sem validar
 * (assume que `auth:sanctum` está cobrindo).
 */
class OauthAuthenticator
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('finalization.oauth_enabled', false)) {
            return $next($request);
        }

        $authHeader = $request->header('Authorization', '');
        if (! is_string($authHeader) || ! str_starts_with($authHeader, 'Bearer stub.')) {
            // Não é OAuth — deixa o stack continuar (Sanctum tentará validar).
            return $next($request);
        }

        $token = substr($authHeader, strlen('Bearer '));
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        $payload = json_decode(base64_decode($parts[1], true) ?: '', true);
        if (! is_array($payload) || ! isset($payload['tenant_id'], $payload['exp'])) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        if ($payload['exp'] < now()->timestamp) {
            return response()->json(['error' => 'token_expired'], 401);
        }

        // Injeta tenant_id no atributo da request — controllers usam via `$request->attributes->get('tenant_id')`.
        $request->attributes->set('oauth.tenant_id', (int) $payload['tenant_id']);
        $request->attributes->set('oauth.scope', (string) ($payload['scope'] ?? ''));

        return $next($request);
    }
}
