<?php

declare(strict_types=1);

namespace App\Support\Metrics;

/**
 * Feature 015 — Métricas Prometheus da IA Matricial (R11).
 *
 * Degrada para `Log::debug` quando `promphp/prometheus_client_php` ausente
 * (herda lifecycle defensivo de {@see AbstractModuleMetrics}).
 */
final class AiMetrics extends AbstractModuleMetrics implements AiMetricsContract
{
    public function responseLatency(float $seconds): void
    {
        $this->recordHistogramOrLog(
            'ai_response_latency_seconds',
            [],
            $seconds,
            'Latência de geração da resposta da IA (alvo p95 ≤ 5s).',
        );
    }

    public function message(int $tenantId): void
    {
        $this->recordCounterOrLog(
            'ai_messages_total',
            ['tenant' => (string) $tenantId],
            'Total de respostas enviadas pela IA por tenant.',
        );
    }

    public function escalation(int $tenantId, string $reason): void
    {
        $this->recordCounterOrLog(
            'ai_escalation_total',
            ['tenant' => (string) $tenantId, 'reason' => $reason],
            'Total de escalonamentos da IA para humano por tenant/motivo.',
        );
    }

    public function toolRoundTrips(int $tenantId, int $count): void
    {
        $this->recordHistogramOrLog(
            'ai_tool_round_trips',
            ['tenant' => (string) $tenantId],
            (float) $count,
            'Round-trips de ferramenta por resposta da IA (feature 017, alvo ≤ 3).',
        );
    }

    // -------------------------------------------------------------------------
    // Feature 018 — Coalescência híbrida (US1, T039)
    // -------------------------------------------------------------------------

    /**
     * Quantidade de mensagens coalescidas em um único turno (FR-005).
     */
    public function coalesceMessagesPerTurn(int $tenantId, int $count): void
    {
        $this->recordHistogramOrLog(
            'ai_coalesce_messages_per_turn',
            ['tenant' => (string) $tenantId],
            (float) $count,
            'Mensagens coalescidas por turno da IA (feature 018, US1).',
        );
    }

    /**
     * Reprocessos (cancel-and-reprocess) por turno (FR-004 cap = 3).
     */
    public function coalesceReprocessCount(int $tenantId, int $count): void
    {
        $this->recordHistogramOrLog(
            'ai_coalesce_reprocess_count',
            ['tenant' => (string) $tenantId],
            (float) $count,
            'Reprocessos da IA por turno coalescido (feature 018, US1, cap 3).',
        );
    }

    /**
     * Razão de flush de um turno coalescido.
     * `reason` ∈ {`passive_debounce_elapsed`, `max_turn_seconds`, `max_reprocesses_reached`}.
     */
    public function coalesceFlush(int $tenantId, string $reason): void
    {
        $this->recordCounterOrLog(
            'ai_coalesce_flush_reason_total',
            ['tenant' => (string) $tenantId, 'reason' => $reason],
            'Razão de flush de turnos coalescidos (feature 018, US1).',
        );
    }

    // -------------------------------------------------------------------------
    // Feature 018 — Servidor MCP (US7, T039)
    // -------------------------------------------------------------------------

    /**
     * Latência de uma chamada MCP em segundos.
     * `outcome` ∈ {`success`, `error`}; `source` ∈ {`production`, `sandbox`}.
     */
    public function mcpRequestDuration(string $capability, string $outcome, string $source, float $seconds): void
    {
        $this->recordHistogramOrLog(
            'ai_mcp_request_duration_seconds',
            ['capability' => $capability, 'outcome' => $outcome, 'source' => $source],
            $seconds,
            'Latência de chamadas MCP por capability/outcome/source (feature 018, US7).',
        );
    }

    /**
     * Estado atual do circuit breaker do MCP.
     * 0 = closed, 1 = half_open, 2 = open.
     */
    public function mcpCircuitState(int $state): void
    {
        $this->recordGaugeOrLog(
            'ai_mcp_circuit_state',
            [],
            (float) $state,
            'Estado do circuit breaker do MCP: 0=closed, 1=half_open, 2=open (feature 018, US7).',
        );
    }

    /**
     * Transição do circuit breaker do MCP.
     * `to` ∈ {`open`, `half_open`, `closed`}; `source` ∈ {`automatic`, `manual_flag`}.
     */
    public function mcpCircuitTransition(string $to, string $source): void
    {
        $this->recordCounterOrLog(
            'ai_mcp_circuit_transitions_total',
            ['to' => $to, 'source' => $source],
            'Transições do circuit breaker do MCP (feature 018, US7).',
        );
    }

    // -------------------------------------------------------------------------
    // Feature 018 — Áudio (US4 + US5, T039)
    // -------------------------------------------------------------------------

    public function sttDuration(string $provider, string $outcome, float $seconds): void
    {
        $this->recordHistogramOrLog(
            'ai_stt_duration_seconds',
            ['provider' => $provider, 'outcome' => $outcome],
            $seconds,
            'Latência de STT (Speech-to-Text) por provedor (feature 018, US4).',
        );
    }

    public function ttsDuration(string $provider, string $outcome, float $seconds): void
    {
        $this->recordHistogramOrLog(
            'ai_tts_duration_seconds',
            ['provider' => $provider, 'outcome' => $outcome],
            $seconds,
            'Latência de TTS (Text-to-Speech) por provedor (feature 018, US5).',
        );
    }

    public function ttsFallbackToText(string $provider, string $reason): void
    {
        $this->recordCounterOrLog(
            'ai_tts_fallback_to_text_total',
            ['provider' => $provider, 'reason' => $reason],
            'Fallbacks de TTS para texto sob falha (feature 018, US5, FR-034).',
        );
    }

    // -------------------------------------------------------------------------
    // Feature 018 — Rate limit (Polish T200-T207, T039)
    // -------------------------------------------------------------------------

    public function rateLimitCooldownActivated(int $tenantId, string $layer): void
    {
        $this->recordCounterOrLog(
            'ai_rate_limit_cooldown_active_total',
            ['tenant' => (string) $tenantId, 'layer' => $layer],
            'Cooldowns ativados por rate limit (feature 018, FR-008b).',
        );
    }

    // -------------------------------------------------------------------------
    // Feature 018 — Kanban (US3, T039)
    // -------------------------------------------------------------------------

    public function kanbanCurationEvent(int $tenantId, string $source, string $eventKind): void
    {
        $this->recordCounterOrLog(
            'ai_kanban_curation_events_total',
            ['tenant' => (string) $tenantId, 'source' => $source, 'event_kind' => $eventKind],
            'Eventos de auto-curadoria do kanban (feature 018, US3, FR-022).',
        );
    }
}
