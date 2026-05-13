<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace App\Support\Metrics;

use Illuminate\Support\Facades\Log;

/**
 * T092 — Wrapper de métricas Prometheus para o domínio Auth (Fase 4 Lote J).
 *
 * Expõe 4 métricas conforme Princípio V (Observabilidade):
 *
 *  - `paciente360_auth_login_total{result}`           — counter
 *  - `paciente360_auth_token_emitido_total`           — counter
 *  - `paciente360_auth_token_revogado_total{motivo}`  — counter
 *  - `paciente360_auth_active_tokens`                 — gauge
 *
 * Mesma estratégia defensiva de `MessagingMetrics`: classes Prometheus acessadas
 * via strings (sem `use`) para que o arquivo carregue mesmo quando o pacote
 * `promphp/prometheus_client_php` não está instalado. Nesse caso degrada para
 * `Log::debug` estruturado.
 *
 * @see App\Support\Metrics\MessagingMetrics (pattern original)
 * @see specs/004-token-auth-migration/spec.md §Princípio V
 */
final class AuthMetrics implements AuthMetricsContract
{
    private const REGISTRY_CLASS = 'Prometheus\\CollectorRegistry';

    private readonly bool $available;

    /** @var array<string, mixed> */
    private array $registry = [];

    public function __construct()
    {
        $this->available = class_exists(self::REGISTRY_CLASS);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    public function loginTotal(string $result): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_auth_login_total',
            labels: ['result' => $result],
            help: 'Total de tentativas de login Bearer por resultado.',
        );
    }

    public function tokenEmitidoTotal(): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_auth_token_emitido_total',
            labels: [],
            help: 'Total de Personal Access Tokens emitidos.',
        );
    }

    public function tokenRevogadoTotal(string $motivo): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_auth_token_revogado_total',
            labels: ['motivo' => $motivo],
            help: 'Total de Personal Access Tokens revogados por motivo.',
        );
    }

    public function activeTokens(int $count): void
    {
        $this->recordGaugeOrLog(
            name: 'paciente360_auth_active_tokens',
            labels: [],
            value: (float) $count,
            help: 'Personal Access Tokens ativos (não-expirados, não-revogados).',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array<string, string> $labels
     */
    private function recordCounterOrLog(string $name, array $labels, string $help): void
    {
        if (! $this->available) {
            Log::debug('metrics.counter', array_merge(['metric' => $name, 'delta' => 1], $labels));

            return;
        }

        try {
            $registry = $this->getCollectorRegistry();
            $counter = $registry->getOrRegisterCounter(
                namespace: '',
                name: $name,
                help: $help,
                labels: array_keys($labels),
            );
            $counter->inc(array_values($labels));
        } catch (\Throwable $e) {
            Log::warning('metrics.counter.error', ['metric' => $name, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param array<string, string> $labels
     */
    private function recordGaugeOrLog(string $name, array $labels, float $value, string $help): void
    {
        if (! $this->available) {
            Log::debug('metrics.gauge', array_merge(['metric' => $name, 'value' => $value], $labels));

            return;
        }

        try {
            $registry = $this->getCollectorRegistry();
            $gauge = $registry->getOrRegisterGauge(
                namespace: '',
                name: $name,
                help: $help,
                labels: array_keys($labels),
            );
            $gauge->set($value, array_values($labels));
        } catch (\Throwable $e) {
            Log::warning('metrics.gauge.error', ['metric' => $name, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @throws \RuntimeException se o storage não puder ser inicializado
     */
    private function getCollectorRegistry(): object
    {
        if (isset($this->registry['instance'])) {
            return $this->registry['instance'];
        }

        $adapter = config('prometheus.storage_adapter', 'memory');

        $storage = match ($adapter) {
            'redis' => new ('Prometheus\\Storage\\Redis')([
                'host' => config('prometheus.redis.host', '127.0.0.1'),
                'port' => (int) config('prometheus.redis.port', 6379),
                'password' => config('prometheus.redis.password'),
                'database' => (int) config('prometheus.redis.database', 0),
            ]),
            'apc' => new ('Prometheus\\Storage\\APC'),
            default => new ('Prometheus\\Storage\\InMemory'),
        };

        $this->registry['instance'] = new (self::REGISTRY_CLASS)($storage);

        return $this->registry['instance'];
    }
}
