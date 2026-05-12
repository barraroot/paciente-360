<?php

namespace App\Support\Metrics;

/**
 * T269 — Contrato para as 6 métricas Prometheus de messaging.
 *
 * Extraído para permitir mocking em testes e inversão de dependência
 * quando necessário (ex.: métricas no-op em ambientes específicos).
 *
 * @see MessagingMetrics
 */
interface MessagingMetricsContract
{
    /**
     * Incrementa o counter de webhooks recebidos.
     *
     * @param string $provider Provider que originou o webhook (e.g. 'twilio', 'meta')
     * @param string $status Resultado do processamento (e.g. 'received', 'duplicate', 'invalid')
     */
    public function webhookReceived(string $provider, string $status): void;

    /**
     * Registra a duração de processamento de um webhook (histograma).
     *
     * @param string $provider Provider que originou o webhook
     * @param float $seconds Duração em segundos
     */
    public function webhookProcessingDuration(string $provider, float $seconds): void;

    /**
     * Incrementa o counter de mensagens outbound.
     *
     * @param string $provider Provider de envio (e.g. 'twilio', 'meta')
     * @param string $status Resultado do envio (e.g. 'sent', 'failed')
     */
    public function outboundMessage(string $provider, string $status): void;

    /**
     * Atualiza o gauge de tamanho de fila.
     *
     * @param string $queue Nome da fila (e.g. 'webhooks-meta', 'outbound-messages')
     * @param int $size Número de jobs pendentes
     */
    public function queueSize(string $queue, int $size): void;

    /**
     * Atualiza o gauge de estado do circuit breaker.
     *
     * @param string $provider Provider cujo circuit breaker está sendo monitorado
     * @param int $state 0=closed, 1=half_open, 2=open
     */
    public function circuitBreakerState(string $provider, int $state): void;

    /**
     * Atualiza o gauge de conversas ativas por tenant e canal.
     *
     * @param int $tenantId ID do tenant
     * @param string $channel Tipo de canal (e.g. 'whatsapp', 'instagram', 'web')
     * @param int $count Número de conversas abertas ou pendentes
     */
    public function conversationsActive(int $tenantId, string $channel, int $count): void;
}
