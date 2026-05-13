<?php

namespace App\Support\Metrics;

/**
 * **T024** — Contrato de métricas Prometheus do domínio Agenda (Fase 5).
 *
 * 6 contadores/gauges + 1 histograma cobrindo o ciclo de vida da consulta + sync Google.
 * Conforme Princípio V (Observabilidade) — `plan.md` Constitution Check.
 */
interface AgendaMetricsContract
{
    public function appointmentCreatedTotal(string $type, string $channelOrigin): void;

    public function appointmentCanceledTotal(string $quem): void;

    public function appointmentNoShowTotal(): void;

    public function confirmationResponseTotal(string $kind, string $result): void;

    public function waitlistNotificationTotal(string $result): void;

    public function calendarSyncStatus(string $provider, string $status, int $count): void;

    public function calendarSyncLatencySeconds(string $operation, float $seconds): void;
}
