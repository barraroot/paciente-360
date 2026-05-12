<?php

namespace App\Domain\Messaging\Infrastructure\CircuitBreaker;

use App\Support\Metrics\MessagingMetricsContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * T044 — Instância de circuit breaker para um provider específico.
 *
 * Estado persistido em Redis via Cache facade:
 *  - `cb:{provider}:opened_at`     — timestamp UNIX da abertura (string|null)
 *  - `cb:{provider}:failure_count` — contador de falhas na janela atual (int)
 *
 * Máquina de estados (research R6):
 *  - closed    → open      : threshold falhas em windowSeconds
 *  - open      → half_open : após recoverySeconds desde opened_at
 *  - half_open → closed    : 1 chamada bem-sucedida
 *  - half_open → open      : 1 chamada falha
 *
 * Leituras de estado são O(1) via Redis GET. Escrita de falha é O(1) via INCR + EXPIRE.
 *
 * T270 — métricas Prometheus:
 *  - `paciente360_circuit_breaker_state{provider}` — gauge atualizado em
 *    toda transição de estado (open, recordSuccess, recordFailure).
 *
 * @see CircuitBreakerService
 * @see specs/003-omnichannel-inbox/research.md — R6
 */
final class CircuitBreakerInstance
{
    /** Mapeamento de nome de estado para valor numérico da métrica. */
    private const STATE_METRIC = [
        'closed' => 0,
        'half_open' => 1,
        'open' => 2,
    ];

    public function __construct(
        private readonly string $provider,
        private readonly int $threshold,
        private readonly int $windowSeconds,
        private readonly int $recoverySeconds,
    ) {}

    /**
     * Executa o callable protegido pelo circuit breaker.
     *
     * @throws CircuitOpenException quando o circuito está aberto
     * @throws \Throwable a exceção original quando o callable falha (closed ou half_open)
     */
    public function call(callable $fn): mixed
    {
        if ($this->state() === 'open') {
            throw new CircuitOpenException($this->provider);
        }

        try {
            $result = $fn();
            $this->recordSuccess();

            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();

            throw $e;
        }
    }

    /**
     * Estado atual do circuito.
     *
     * @return 'closed'|'open'|'half_open'
     */
    public function state(): string
    {
        $openedAt = $this->getOpenedAt();

        if ($openedAt === null) {
            return 'closed';
        }

        $elapsedSeconds = Carbon::now()->diffInSeconds(Carbon::createFromTimestamp($openedAt), false) * -1;

        if ($elapsedSeconds < $this->recoverySeconds) {
            return 'open';
        }

        return 'half_open';
    }

    /**
     * Registra uma chamada bem-sucedida.
     *
     * - Se half_open → fecha o circuito (deleta opened_at e failure_count).
     * - Se closed    → reseta o contador de falhas.
     */
    public function recordSuccess(): void
    {
        $currentState = $this->state();

        if ($currentState === 'half_open') {
            $this->clearState();
            $this->publishStateMetric('closed');

            return;
        }

        // closed: reseta o failure_count para que o provider não acumule "créditos negativos"
        Cache::forget($this->failureCountKey());
    }

    /**
     * Registra uma falha.
     *
     * - Se half_open → reabre o circuito (atualiza opened_at = agora).
     * - Se closed    → incrementa failure_count com TTL = windowSeconds;
     *                  se atingir threshold, abre (seta opened_at = agora).
     */
    public function recordFailure(): void
    {
        $currentState = $this->state();

        if ($currentState === 'half_open') {
            $this->open();

            return;
        }

        // closed: incrementa com TTL de janela deslizante
        $count = $this->incrementFailureCount();

        if ($count >= $this->threshold) {
            $this->open();
        }
    }

    /**
     * Reseta completamente o estado do circuit breaker.
     * Útil para testes e para reset manual via admin.
     */
    public function reset(): void
    {
        $this->clearState();
    }

    /**
     * Abre o circuito gravando o timestamp atual em opened_at.
     * Não expira — o transition para half_open é por tempo (não TTL do Redis).
     */
    private function open(): void
    {
        Cache::forever($this->openedAtKey(), Carbon::now()->timestamp);
        $this->publishStateMetric('open');
    }

    /**
     * Limpa todos os keys de estado do Redis para este provider.
     */
    private function clearState(): void
    {
        Cache::forget($this->openedAtKey());
        Cache::forget($this->failureCountKey());
    }

    /**
     * Retorna o timestamp UNIX de abertura, ou null se o circuito está fechado.
     */
    private function getOpenedAt(): ?int
    {
        $value = Cache::get($this->openedAtKey());

        return $value !== null ? (int) $value : null;
    }

    /**
     * Incrementa o failure_count e garante TTL da janela deslizante.
     * Usa add (para definir TTL na primeira escrita) + increment para atômico.
     */
    private function incrementFailureCount(): int
    {
        $key = $this->failureCountKey();

        // Tenta adicionar com TTL (primeira falha na janela). Se a chave já existia,
        // add() retorna false e o TTL existente é mantido — comportamento correto.
        Cache::add($key, 0, $this->windowSeconds);

        return (int) Cache::increment($key);
    }

    /**
     * Publica a transição de estado para a métrica Prometheus.
     *
     * Captura falhas silenciosamente — o circuit breaker não deve quebrar
     * porque o exporter de métricas está indisponível.
     *
     * @param 'closed'|'open'|'half_open' $stateName
     */
    private function publishStateMetric(string $stateName): void
    {
        try {
            /** @var MessagingMetricsContract $metrics */
            $metrics = app(MessagingMetricsContract::class);
            $metrics->circuitBreakerState($this->provider, self::STATE_METRIC[$stateName]);
        } catch (\Throwable) {
            // falha silenciosa — métricas são observabilidade, não controle de fluxo
        }
    }

    private function openedAtKey(): string
    {
        return "cb:{$this->provider}:opened_at";
    }

    private function failureCountKey(): string
    {
        return "cb:{$this->provider}:failure_count";
    }
}
