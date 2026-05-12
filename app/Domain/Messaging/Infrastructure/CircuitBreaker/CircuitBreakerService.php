<?php

namespace App\Domain\Messaging\Infrastructure\CircuitBreaker;

/**
 * T044 — Factory/Registry do circuit breaker por provider.
 *
 * Mantém instâncias em cache por provider dentro do ciclo de vida
 * do container (singleton em AppServiceProvider se desejado).
 *
 * Configurações lidas de `config('messaging.circuit_breaker.*')`:
 *  - threshold        : falhas para abrir (default 5)
 *  - window_seconds   : janela de contagem (default 60s)
 *  - recovery_seconds : tempo até half_open (default 30s)
 *
 * Uso:
 * ```php
 * $cb = app(CircuitBreakerService::class)->for('twilio');
 * $result = $cb->call(fn() => $this->client->send(...));
 * ```
 *
 * @see CircuitBreakerInstance
 * @see specs/003-omnichannel-inbox/research.md — R6
 */
final class CircuitBreakerService
{
    /** @var array<string, CircuitBreakerInstance> */
    private array $instances = [];

    public function for(string $provider): CircuitBreakerInstance
    {
        if (! isset($this->instances[$provider])) {
            $this->instances[$provider] = new CircuitBreakerInstance(
                provider: $provider,
                threshold: (int) config('messaging.circuit_breaker.threshold', 5),
                windowSeconds: (int) config('messaging.circuit_breaker.window_seconds', 60),
                recoverySeconds: (int) config('messaging.circuit_breaker.recovery_seconds', 30),
            );
        }

        return $this->instances[$provider];
    }
}
