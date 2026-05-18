<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace App\Support\Metrics;

use Illuminate\Support\Facades\Log;

final class PrescriptionMetrics implements PrescriptionMetricsContract
{
    private const REGISTRY_CLASS = 'Prometheus\\CollectorRegistry';

    private readonly bool $available;

    /** @var array<string, mixed> */
    private array $registry = [];

    public function __construct()
    {
        $this->available = class_exists(self::REGISTRY_CLASS);
    }

    public function alertDispatchedTotal(int $tenantId, string $alertStep, string $status): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_prescription_alerts_dispatched_total',
            labels: [
                'tenant_id' => (string) $tenantId,
                'alert_step' => $alertStep,
                'status' => $status,
            ],
            help: 'Alertas de receita despachados por tenant, checkpoint e status.',
        );
    }

    public function renewalConversionRate(int $tenantId, float $rate): void
    {
        $this->recordGaugeOrLog(
            name: 'paciente360_prescription_renewal_conversion_rate',
            labels: ['tenant_id' => (string) $tenantId],
            value: $rate,
            help: 'Taxa de conversao de renovacoes de receita por tenant.',
        );
    }

    public function controlledAccessDeniedTotal(int $tenantId): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_prescription_controlled_access_denied_total',
            labels: ['tenant_id' => (string) $tenantId],
            help: 'Tentativas negadas de acesso a receitas controladas por tenant.',
        );
    }

    public function pdfUploadedTotal(string $status): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_prescription_pdfs_uploaded_total',
            labels: ['status' => $status],
            help: 'Uploads de PDF de receita por status.',
        );
    }

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
            $counter = $this->getCollectorRegistry()->getOrRegisterCounter(
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
            $gauge = $this->getCollectorRegistry()->getOrRegisterGauge(
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
