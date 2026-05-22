<?php

declare(strict_types=1);

namespace App\Support\Metrics;

/**
 * **T184 (Fase 8 — Lote C US-9.2)** — Métricas Prometheus do módulo Campanhas.
 *
 * Conforme plan.md §7:
 *   - campaign_dispatched_total{tenant, status}                 (counter)
 *   - campaign_blocked_total{reason, tenant}                    (counter)
 *   - campaign_recipients_total{campaign_id}                    (gauge)
 *   - campaign_dispatch_duration_seconds{tenant}                (histogram)
 *
 * Estende {@see AbstractModuleMetrics} (T005).
 */
final class CampaignMetrics extends AbstractModuleMetrics
{
    /**
     * Counter: campanha disparada (1 incremento por campanha, por status final).
     */
    public function campaignDispatched(int $tenantId, string $status): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_campaign_dispatched_total',
            labels: ['tenant' => (string) $tenantId, 'status' => $status],
            help: 'Total de campanhas disparadas por tenant e status final.',
        );
    }

    /**
     * Counter: destinatário bloqueado por motivo de conformidade.
     *
     * Espera-se spike quando tenant tem muitos pacientes sem opt-in marketing
     * — alerta para sales push em coleta de consentimento.
     */
    public function campaignBlocked(string $reason, int $tenantId): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_campaign_blocked_total',
            labels: ['reason' => $reason, 'tenant' => (string) $tenantId],
            help: 'Total de envios de campanha bloqueados por motivo de conformidade.',
        );
    }

    /**
     * Gauge: número de destinatários (snapshot) de uma campanha.
     */
    public function campaignRecipientsCount(int $campaignId, int $count): void
    {
        $this->recordGaugeOrLog(
            name: 'paciente360_campaign_recipients_total',
            labels: ['campaign_id' => (string) $campaignId],
            value: (float) $count,
            help: 'Número de destinatários elegíveis de uma campanha.',
        );
    }

    /**
     * Histogram: duração total do dispatch (do início até campanha=completed).
     * Bucket sugerido: 30s, 1m, 5m, 15m, 30m, 1h.
     */
    public function campaignDispatchDuration(int $tenantId, float $seconds): void
    {
        $this->recordHistogramOrLog(
            name: 'paciente360_campaign_dispatch_duration_seconds',
            labels: ['tenant' => (string) $tenantId],
            value: $seconds,
            help: 'Duração total de dispatch de campanha (segundos).',
            buckets: [30.0, 60.0, 300.0, 900.0, 1800.0, 3600.0],
        );
    }

    /**
     * Counter: mensagem de campanha enviada com sucesso.
     */
    public function messageSent(int $tenantId, string $channel): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_campaign_message_sent_total',
            labels: ['tenant' => (string) $tenantId, 'channel' => $channel],
            help: 'Total de mensagens de campanha enviadas com sucesso.',
        );
    }
}
