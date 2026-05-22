<?php

declare(strict_types=1);

namespace App\Domain\SuperAdmin\Models;

/**
 * **T131 (Fase 8 — Lote B US-12.3)** — Categorias monitoradas pelo AnomalyDetector.
 *
 * Thresholds em `config('finalization.anomaly_thresholds')`.
 */
enum AnomalyCategory: string
{
    case ConversionDrop = 'conversion_drop';
    case AiUsageSpike = 'ai_usage_spike';
    case WebhookFailureRate = 'webhook_failure_rate';
    case PaymentOverdue = 'payment_overdue';

    public function label(): string
    {
        return match ($this) {
            self::ConversionDrop => 'Queda de conversão trial→pago',
            self::AiUsageSpike => 'Pico de consumo de IA',
            self::WebhookFailureRate => 'Taxa de falha de webhook',
            self::PaymentOverdue => 'Inadimplência prolongada',
        };
    }
}
