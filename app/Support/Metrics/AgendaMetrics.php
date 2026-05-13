<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace App\Support\Metrics;

use Illuminate\Support\Facades\Log;

/**
 * **T024** — Wrapper de métricas Prometheus do domínio Agenda (Fase 5).
 *
 * 7 métricas conforme Princípio V (Observabilidade):
 *
 *  - `paciente360_appointment_created_total{type,channel_origin}`     — counter
 *  - `paciente360_appointment_canceled_total{quem}`                   — counter
 *  - `paciente360_appointment_no_show_total`                          — counter
 *  - `paciente360_confirmation_response_total{kind,result}`           — counter
 *  - `paciente360_waitlist_notification_total{result}`                — counter
 *  - `paciente360_calendar_sync_status{provider,status}`              — gauge
 *  - `paciente360_calendar_sync_latency_seconds{operation}`           — histogram
 *
 * Mesma estratégia defensiva de `AuthMetrics`/`MessagingMetrics` — degrada para
 * `Log::debug` quando o pacote `promphp/prometheus_client_php` não está disponível.
 *
 * @see App\Support\Metrics\AuthMetrics (pattern original)
 */
final class AgendaMetrics implements AgendaMetricsContract
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

    public function appointmentCreatedTotal(string $type, string $channelOrigin): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_appointment_created_total',
            labels: ['type' => $type, 'channel_origin' => $channelOrigin],
            help: 'Consultas criadas por tipo e canal de origem.',
        );
    }

    public function appointmentCanceledTotal(string $quem): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_appointment_canceled_total',
            labels: ['quem' => $quem],
            help: 'Consultas canceladas por origem do cancelamento.',
        );
    }

    public function appointmentNoShowTotal(): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_appointment_no_show_total',
            labels: [],
            help: 'Consultas marcadas como nao_realizada (no-show).',
        );
    }

    public function confirmationResponseTotal(string $kind, string $result): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_confirmation_response_total',
            labels: ['kind' => $kind, 'result' => $result],
            help: 'Respostas de confirmação automática por kind (24h/2h/retry/manual) e resultado (1/2/3/null).',
        );
    }

    public function waitlistNotificationTotal(string $result): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_waitlist_notification_total',
            labels: ['result' => $result],
            help: 'Notificações de lista de espera por resultado (accepted/expired/canceled).',
        );
    }

    public function calendarSyncStatus(string $provider, string $status, int $count): void
    {
        $this->recordGaugeOrLog(
            name: 'paciente360_calendar_sync_status',
            labels: ['provider' => $provider, 'status' => $status],
            value: (float) $count,
            help: 'Total de CalendarSyncAccount por provider e status.',
        );
    }

    public function calendarSyncLatencySeconds(string $operation, float $seconds): void
    {
        $this->recordHistogramOrLog(
            name: 'paciente360_calendar_sync_latency_seconds',
            labels: ['operation' => $operation],
            value: $seconds,
            help: 'Latência das operações de sync com Google Calendar.',
            buckets: [0.1, 0.25, 0.5, 1.0, 2.0, 5.0, 10.0, 30.0],
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** @param array<string, string> $labels */
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

    /** @param array<string, string> $labels */
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

    /**
     * @param array<string, string> $labels
     * @param list<float> $buckets
     */
    private function recordHistogramOrLog(string $name, array $labels, float $value, string $help, array $buckets): void
    {
        if (! $this->available) {
            Log::debug('metrics.histogram', array_merge(['metric' => $name, 'value' => $value], $labels));

            return;
        }

        try {
            $histogram = $this->getCollectorRegistry()->getOrRegisterHistogram(
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
