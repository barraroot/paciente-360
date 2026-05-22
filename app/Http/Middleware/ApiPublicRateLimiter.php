<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * **T219 (Fase 8 — Lote D US-11.2)** — Rate limiter para API pública.
 *
 * Estratégia:
 *   - Limite por token: `plan.api_rate_limit_per_minute` (default 60).
 *   - Cap anti-DDoS por IP: `finalization.api_public_ip_hard_cap_per_minute` (10000).
 *
 * Headers retornados (RFC 6585 compliant):
 *   X-RateLimit-Limit, X-RateLimit-Remaining, Retry-After (em 429).
 */
class ApiPublicRateLimiter
{
    public function __construct(private readonly RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tokenLimit = $this->resolveTokenLimit($request);
        $ipLimit = (int) config('finalization.api_public_ip_hard_cap_per_minute', 10000);

        $tokenKey = $this->tokenKey($request);
        $ipKey = $this->ipKey($request);

        // Por IP (cap anti-DDoS) — verifica primeiro.
        if ($this->limiter->tooManyAttempts($ipKey, $ipLimit)) {
            return $this->buildResponse($ipLimit, $this->limiter->availableIn($ipKey), 'ip_rate_limit_exceeded');
        }

        // Por token (limite do plano).
        if ($this->limiter->tooManyAttempts($tokenKey, $tokenLimit)) {
            return $this->buildResponse($tokenLimit, $this->limiter->availableIn($tokenKey), 'token_rate_limit_exceeded');
        }

        $this->limiter->hit($tokenKey, 60);
        $this->limiter->hit($ipKey, 60);

        $response = $next($request);
        $remaining = max(0, $tokenLimit - $this->limiter->attempts($tokenKey));

        $response->headers->set('X-RateLimit-Limit', (string) $tokenLimit);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);

        return $response;
    }

    private function resolveTokenLimit(Request $request): int
    {
        $user = $request->user();
        $tenant = $user?->tenant ?? null;
        $plan = $tenant?->currentPlan ?? null;

        return (int) ($plan?->api_rate_limit_per_minute ?? 60);
    }

    private function tokenKey(Request $request): string
    {
        $tokenId = $request->user()?->currentAccessToken()?->id ?? 'anonymous';

        return "api_public:token:{$tokenId}";
    }

    private function ipKey(Request $request): string
    {
        return 'api_public:ip:'.$request->ip();
    }

    private function buildResponse(int $limit, int $retryAfter, string $code): Response
    {
        return response()->json([
            'error' => $code,
            'message' => 'Limite de requisições por minuto excedido.',
            'limit_per_minute' => $limit,
            'retry_after_seconds' => $retryAfter,
        ], 429, [
            'X-RateLimit-Limit' => (string) $limit,
            'X-RateLimit-Remaining' => '0',
            'Retry-After' => (string) $retryAfter,
        ]);
    }
}
