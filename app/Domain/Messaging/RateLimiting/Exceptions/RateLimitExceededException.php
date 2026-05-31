<?php

declare(strict_types=1);

namespace App\Domain\Messaging\RateLimiting\Exceptions;

use App\Domain\Messaging\RateLimiting\InboundConversationLimiter;
use RuntimeException;

/**
 * **T200 (Fase 18 — Polish, FR-008a)** — disparada por
 * {@see InboundConversationLimiter}
 * quando uma das 2 camadas de rate limit anti-abuso é excedida.
 *
 * `limiterKey` distingue a origem para o cooldown auditável:
 *   - `per_conversation` (30 msgs / 10min)
 *   - `per_identifier`   (100 msgs / 10min)
 */
final class RateLimitExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $limiterKey,
        public readonly int $availableInSeconds,
        string $message = '',
    ) {
        parent::__construct(
            $message !== '' ? $message : "Rate limit excedido para limiter [{$limiterKey}].",
        );
    }
}
