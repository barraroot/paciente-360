<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace App\Support\Metrics;

use Illuminate\Support\Facades\Log;

/**
 * T269 — Wrapper de métricas Prometheus para o domínio de Messaging.
 *
 * Expõe 6 métricas conforme Princípio V (Observabilidade) e research R7:
 *
 *  - `paciente360_webhook_received_total{provider, status}`       — counter
 *  - `paciente360_webhook_processing_duration_seconds{provider}`  — histogram
 *  - `paciente360_outbound_message_total{provider, status}`       — counter
 *  - `paciente360_queue_size{queue}`                              — gauge
 *  - `paciente360_circuit_breaker_state{provider}`                — gauge (0=closed, 1=half_open, 2=open)
 *  - `paciente360_conversations_active{tenant_id, channel}`       — gauge
 *
 * Quando o cliente Prometheus não estiver disponível (instalação sem
 * `promphp/prometheus_client_php` ou sem `PROMETHEUS_*` env vars),
 * todas as chamadas degradam graciosamente para log estruturado em vez
 * de lançar exceções. Assim o código de negócio não quebra em ambientes
 * que ainda não têm o exporter configurado (CI, dev, Fase 0).
 *
 * IMPORTANTE: as classes Prometheus NÃO são importadas via `use` — pint
 * adicionaria os imports automaticamente, causando fatal error quando o
 * pacote não está instalado. O acesso é feito via strings/variáveis para
 * contornar o auto-resolver do Pint.
 *
 * Para habilitar o storage real, instale o pacote e configure:
 *   - PROMETHEUS_STORAGE_ADAPTER=redis   (ou `memory`, `apc`)
 *   - PROMETHEUS_REDIS_HOST, PROMETHEUS_REDIS_PORT
 *
 * @see specs/003-omnichannel-inbox/research.md — R7
 */
final class MessagingMetrics implements MessagingMetricsContract
{
    /**
     * FQCN do CollectorRegistry — armazenado como string para evitar que pint
     * adicione um `use` no topo do arquivo (o que quebraria quando o pacote
     * não está instalado).
     */
    private const REGISTRY_CLASS = 'Prometheus\\CollectorRegistry';

    /**
     * Buckets (em segundos) que cobrem os percentis esperados de
     * processamento de webhooks: p50 < 0.1s, p95 < 2s, p99 < 10s.
     *
     * @var list<float>
     */
    private const DURATION_BUCKETS = [0.05, 0.1, 0.5, 1.0, 2.0, 5.0, 10.0];

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

    /**
     * Incrementa o counter de webhooks recebidos.
     *
     * @param string $provider Provider que originou o webhook (e.g. 'twilio', 'meta')
     * @param string $status Resultado do processamento (e.g. 'received', 'duplicate', 'invalid')
     */
    public function webhookReceived(string $provider, string $status): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_webhook_received_total',
            labels: ['provider' => $provider, 'status' => $status],
            help: 'Total de webhooks de mensagens recebidos por provider e status.',
        );
    }

    /**
     * Registra a duração de processamento de um webhook (histograma).
     *
     * @param string $provider Provider que originou o webhook
     * @param float $seconds Duração em segundos
     */
    public function webhookProcessingDuration(string $provider, float $seconds): void
    {
        $this->recordHistogramOrLog(
            name: 'paciente360_webhook_processing_duration_seconds',
            labels: ['provider' => $provider],
            value: $seconds,
            buckets: self::DURATION_BUCKETS,
            help: 'Tempo de processamento de webhook por provider (segundos).',
        );
    }

    /**
     * Incrementa o counter de mensagens outbound.
     *
     * @param string $provider Provider de envio (e.g. 'twilio', 'meta')
     * @param string $status Resultado do envio (e.g. 'sent', 'failed')
     */
    public function outboundMessage(string $provider, string $status): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_outbound_message_total',
            labels: ['provider' => $provider, 'status' => $status],
            help: 'Total de mensagens outbound enviadas por provider e status.',
        );
    }

    /**
     * Atualiza o gauge de tamanho de fila.
     *
     * @param string $queue Nome da fila (e.g. 'webhooks-meta', 'outbound-messages')
     * @param int $size Número de jobs pendentes
     */
    public function queueSize(string $queue, int $size): void
    {
        $this->recordGaugeOrLog(
            name: 'paciente360_queue_size',
            labels: ['queue' => $queue],
            value: (float) $size,
            help: 'Número de jobs pendentes por fila.',
        );
    }

    /**
     * Atualiza o gauge de estado do circuit breaker.
     *
     * @param string $provider Provider cujo circuit breaker está sendo monitorado
     * @param int $state 0=closed, 1=half_open, 2=open
     */
    public function circuitBreakerState(string $provider, int $state): void
    {
        $this->recordGaugeOrLog(
            name: 'paciente360_circuit_breaker_state',
            labels: ['provider' => $provider],
            value: (float) $state,
            help: 'Estado do circuit breaker por provider (0=closed, 1=half_open, 2=open).',
        );
    }

    /**
     * Atualiza o gauge de conversas ativas por tenant e canal.
     *
     * @param int $tenantId ID do tenant
     * @param string $channel Tipo de canal (e.g. 'whatsapp', 'instagram', 'web')
     * @param int $count Número de conversas abertas ou pendentes
     */
    public function conversationsActive(int $tenantId, string $channel, int $count): void
    {
        $this->recordGaugeOrLog(
            name: 'paciente360_conversations_active',
            labels: ['tenant_id' => (string) $tenantId, 'channel' => $channel],
            value: (float) $count,
            help: 'Conversas ativas (abertas + pendentes) por tenant e canal.',
        );
    }

    /**
     * Feature 014 — counter de conexões de canal por tenant/provider/status.
     */
    public function channelConnected(int $tenantId, string $provider, string $status): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_channel_connections_total',
            labels: ['tenant' => (string) $tenantId, 'provider' => $provider, 'status' => $status],
            help: 'Total de conexões de canal por tenant, provider e status.',
        );
    }

    /**
     * Feature 014 — counter de desconexões de canal por tenant/provider/motivo.
     */
    public function channelDisconnected(int $tenantId, string $provider, string $reason): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_channel_disconnections_total',
            labels: ['tenant' => (string) $tenantId, 'provider' => $provider, 'reason' => $reason],
            help: 'Total de desconexões de canal por tenant, provider e motivo.',
        );
    }

    /**
     * Feature 014 — counter de reconexões de canal por tenant/provider.
     */
    public function channelReconnected(int $tenantId, string $provider): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_channel_reconnects_total',
            labels: ['tenant' => (string) $tenantId, 'provider' => $provider],
            help: 'Total de reconexões de canal por tenant e provider.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal helpers — delegate to Prometheus or log gracefully
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
     * @param list<float> $buckets
     */
    private function recordHistogramOrLog(string $name, array $labels, float $value, array $buckets, string $help): void
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
     * Retorna (ou cria) o CollectorRegistry configurado via instanciação dinâmica.
     * Classes são instanciadas por string para evitar que pint adicione `use`
     * imports no topo do arquivo (o que quebraria quando o pacote não está instalado).
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
