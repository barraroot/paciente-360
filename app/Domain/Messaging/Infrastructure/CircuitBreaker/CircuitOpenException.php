<?php

namespace App\Domain\Messaging\Infrastructure\CircuitBreaker;

use RuntimeException;

/**
 * T045 — Exceção lançada quando uma chamada é feita a um circuit breaker aberto.
 *
 * Identifica o provider bloqueado para facilitar logging e métricas.
 *
 * @see CircuitBreakerInstance::call()
 * @see specs/003-omnichannel-inbox/research.md — R6 (circuit breaker)
 */
final class CircuitOpenException extends RuntimeException
{
    public function __construct(
        public readonly string $provider,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?: "Circuit breaker is open for provider [{$provider}]. Calls are blocked until recovery.",
            $code,
            $previous,
        );
    }
}
