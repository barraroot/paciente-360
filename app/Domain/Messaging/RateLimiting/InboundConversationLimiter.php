<?php

declare(strict_types=1);

namespace App\Domain\Messaging\RateLimiting;

use App\Domain\Messaging\RateLimiting\Exceptions\RateLimitExceededException;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\RateLimiter;

/**
 * **T200 (Fase 18 — Polish, FR-008a, Q-clarify-5=C)** — rate limit anti-abuso
 * em 2 camadas:
 *
 *   1. `messaging:inbound:per-conversation` — default 30 msgs / 10min.
 *      Captura abuso dentro de uma única thread (paciente specing 100 msgs
 *      em sequência).
 *
 *   2. `messaging:inbound:per-identifier` — default 100 msgs / 10min.
 *      Captura abuso vindo do mesmo telefone/handle através de várias
 *      conversas (raro, mas defense-in-depth).
 *
 * Ambas registradas em {@see RouteServiceProvider::configureRateLimiting}
 * via `RateLimiter::for(..., fn (mixed $key))`. Aqui usamos `RateLimiter::hit`
 * + `tooManyAttempts` com chave manual (não dependemos do middleware HTTP
 * de rate limit — listeners de evento não passam por middleware).
 *
 * Excedido → throw {@see RateLimitExceededException}. O caller decide o que
 * fazer (tipicamente `CooldownService::startFor`).
 */
final class InboundConversationLimiter
{
    /**
     * Verifica AMBAS as camadas e, se OK, INCREMENTA o contador de ambas
     * (cada inbound conta uma vez para cada limiter).
     *
     * Ordem importa: a per-conversation é checada primeiro (sinal mais
     * específico — abuse dentro da thread); se excedida, NÃO contabiliza
     * a per-identifier (evita "vazamento" de contadores quando já estamos
     * sob bloqueio).
     */
    public function checkOrThrow(int $conversationId, int $tenantId, string $identifier): void
    {
        $convKey = "conv:{$tenantId}:{$conversationId}";
        $identKey = "ident:{$tenantId}:".sha1($identifier);

        $perConvWindowS = (int) config('messaging.rate.window_minutes', 10) * 60;
        $perConvMax = (int) config('messaging.rate.per_conversation', 30);
        $perIdentWindowS = $perConvWindowS;
        $perIdentMax = (int) config('messaging.rate.per_identifier', 100);

        if (RateLimiter::tooManyAttempts($convKey, $perConvMax)) {
            throw new RateLimitExceededException(
                limiterKey: 'per_conversation',
                availableInSeconds: RateLimiter::availableIn($convKey),
            );
        }
        if (RateLimiter::tooManyAttempts($identKey, $perIdentMax)) {
            throw new RateLimitExceededException(
                limiterKey: 'per_identifier',
                availableInSeconds: RateLimiter::availableIn($identKey),
            );
        }

        RateLimiter::hit($convKey, $perConvWindowS);
        RateLimiter::hit($identKey, $perIdentWindowS);
    }

    /**
     * Útil em testes/observabilidade — quantas mensagens já bateram cada
     * limiter na janela atual.
     */
    public function snapshot(int $conversationId, int $tenantId, string $identifier): array
    {
        $convKey = "conv:{$tenantId}:{$conversationId}";
        $identKey = "ident:{$tenantId}:".sha1($identifier);

        return [
            'per_conversation' => RateLimiter::attempts($convKey),
            'per_identifier' => RateLimiter::attempts($identKey),
        ];
    }
}
