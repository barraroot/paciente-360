<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * T193 — Middleware de validação de assinatura HMAC SHA256 da Meta.
 *
 * A Meta assina cada requisição POST de webhook com HMAC SHA256 do raw body
 * usando o `app_secret` global da Meta App como chave.
 *
 * Header: `X-Hub-Signature-256: sha256=<hex_digest>`
 *
 * Princípio I — Segurança: rejeita com 403 qualquer webhook sem assinatura
 * válida antes de chegar ao controller.
 *
 * GET handshake não tem assinatura (sem body) → bypassado.
 *
 * @see https://developers.facebook.com/docs/messenger-platform/webhooks#security
 */
class ValidateMetaSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        // GET handshake — sem body, sem assinatura
        if ($request->isMethod('GET')) {
            return $next($request);
        }

        $signature = $request->header('X-Hub-Signature-256');

        if (empty($signature) || ! str_starts_with($signature, 'sha256=')) {
            Log::warning('ValidateMetaSignature: header ausente ou malformado', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'signature_present' => ! empty($signature),
            ]);

            return response()->json(['error' => 'missing_signature'], 403);
        }

        $appSecret = config('messaging.providers.meta.app_secret', '');

        if (empty($appSecret)) {
            // app_secret não configurado: rejeita com 403 (mais seguro que 500)
            // Em produção isso indica misconfiguration; em testes é estado esperado.
            Log::error('ValidateMetaSignature: META_APP_SECRET não configurado — rejeitando webhook');

            return response()->json(['error' => 'invalid_signature'], 403);
        }

        // Meta assina o RAW body antes do parsing — NUNCA usar $request->all()
        $rawBody = $request->getContent();
        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $appSecret);

        if (! hash_equals($expected, $signature)) {
            Log::warning('ValidateMetaSignature: assinatura inválida', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json(['error' => 'invalid_signature'], 403);
        }

        return $next($request);
    }
}
