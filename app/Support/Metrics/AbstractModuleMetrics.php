<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace App\Support\Metrics;

use Illuminate\Support\Facades\Log;

/**
 * **T005** — Classe base padronizando wrappers de métricas Prometheus para os 5
 * módulos da Fase 8 (Campaigns, Reports, Webhooks, SuperAdmin, Privacy).
 *
 * Repete a estratégia defensiva já consolidada em {@see AuthMetrics} e
 * {@see PrescriptionMetrics}: classes Prometheus acessadas via strings para
 * que o arquivo carregue mesmo quando `promphp/prometheus_client_php` não
 * está instalado. Nesse caso degrada para `Log::debug` estruturado.
 *
 * Subclasses concretas (CampaignMetrics, ReportMetrics, WebhookMetrics,
 * SuperAdminMetrics, PrivacyMetrics) usam estes 3 helpers para registrar
 * counters/gauges/histograms sem reimplementar lifecycle do registry.
 *
 * @see specs/008-finalizacao-mvp/plan.md §7
 */
abstract class AbstractModuleMetrics
{
    private const REGISTRY_CLASS = 'Prometheus\\CollectorRegistry';

    protected readonly bool $available;

    /** @var array<string, object> */
    private array $registry = [];

    public function __construct()
    {
        $this->available = class_exists(self::REGISTRY_CLASS);
    }

    /**
     * Incrementa um counter pelo nome com labels.
     *
     * @param array<string, string> $labels
     */
    protected function recordCounterOrLog(string $name, array $labels, string $help): void
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
     * Seta um gauge a um valor absoluto.
     *
     * @param array<string, string> $labels
     */
    protected function recordGaugeOrLog(string $name, array $labels, float $value, string $help): void
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
     * Observa um valor em um histogram (para latências, durations).
     *
     * @param array<string, string> $labels
     * @param list<float>|null $buckets buckets padrão se null (Prometheus default)
     */
    protected function recordHistogramOrLog(string $name, array $labels, float $value, string $help, ?array $buckets = null): void
    {
        if (! $this->available) {
            Log::debug('metrics.histogram', array_merge(['metric' => $name, 'value' => $value], $labels));

            return;
        }

        try {
            $registry = $this->getCollectorRegistry();
            $histogram = $registry->getOrRegisterHistogram(
                namespace: '',
                name: $name,
                help: $help,
                labels: array_keys($labels),
                buckets: $buckets,
            );
            $histogram->observe($value, array_values($labels));
        } catch (\Throwable $e) {
            Log::warning('metrics.histogram.error', ['metric' => $name, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Resolve o CollectorRegistry compartilhado por instância.
     *
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
