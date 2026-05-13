<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Seta headers de segurança HTTP em todas as responses (T030 — amendment v1.4.0 §CSP gate).
 *
 * Headers sempre presentes (todos os ambientes):
 *  - `Strict-Transport-Security` — HSTS 1 ano + includeSubDomains + preload
 *  - `X-Frame-Options: DENY` — previne clickjacking
 *  - `X-Content-Type-Options: nosniff` — previne MIME sniffing
 *  - `Referrer-Policy: strict-origin-when-cross-origin`
 *
 * CSP (Content Security Policy):
 *  - **Produção**: estrita com nonce gerado por request — sem `unsafe-inline` / `unsafe-eval`.
 *    Nonce salvo em `$request->attributes->set('csp_nonce', $nonce)` para Blade usar.
 *  - **Local/test/staging**: permissiva (permite `unsafe-inline` + `unsafe-eval`
 *    para hot reload do Vite).
 *
 * @see specs/004-token-auth-migration/spec.md §NC-3 (CSP gate de release obrigatório)
 * @see specs/004-token-auth-migration/research.md R4 (CSP strategy)
 */
class SetSecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->setAlwaysOnHeaders($response);
        $this->setContentSecurityPolicy($request, $response);

        return $response;
    }

    /**
     * Headers presentes em todos os ambientes.
     */
    private function setAlwaysOnHeaders(Response $response): void
    {
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /**
     * Content-Security-Policy diferenciada por ambiente.
     */
    private function setContentSecurityPolicy(Request $request, Response $response): void
    {
        if (app()->environment('production')) {
            $nonce = bin2hex(random_bytes(16));
            $request->attributes->set('csp_nonce', $nonce);

            // T095/T096 (Fase 4 Lote J) — connect-src estendido para cobrir:
            //  - Reverb WSS (definido via env CSP_REVERB_HOST)
            //  - S3 media presigned URLs (envCSP_MEDIA_HOST — ex.: *.amazonaws.com)
            //  - API self (CDN SPA → API decoupled em domínios distintos)
            // Defaults seguros para o domain prod alvo do Paciente360.
            $reverbHost = (string) config('csp.reverb_host', 'wss://reverb.crm.com.br');
            $mediaHost = (string) config('csp.media_host', 'https://*.amazonaws.com');
            $apiHost = (string) config('csp.api_host', 'https://api.crm.com.br');

            $csp = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}'",
                "style-src 'self' 'nonce-{$nonce}'",
                "img-src 'self' data: https:",
                "connect-src 'self' {$reverbHost} {$mediaHost} {$apiHost}",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'",
            ]);
        } else {
            // Desenvolvimento / staging / testes — CSP permissiva para Vite HMR.
            $csp = implode('; ', [
                "default-src 'self' 'unsafe-inline' 'unsafe-eval'",
                "img-src 'self' data: https:",
                "connect-src 'self' ws: wss:",
                "frame-ancestors 'none'",
                "base-uri 'self'",
            ]);
        }

        $response->headers->set('Content-Security-Policy', $csp);
    }
}
