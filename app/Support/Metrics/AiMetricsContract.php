<?php

declare(strict_types=1);

namespace App\Support\Metrics;

/**
 * Feature 015 — Contrato das métricas Prometheus da IA Matricial (R11).
 *
 * @see AiMetrics
 */
interface AiMetricsContract
{
    /**
     * Observa a latência de geração da resposta (segundos). Alvo p95 ≤ 5s.
     */
    public function responseLatency(float $seconds): void;

    /**
     * Incrementa o total de mensagens da IA por tenant (consumo mensal).
     */
    public function message(int $tenantId): void;

    /**
     * Incrementa o total de escalonamentos para humano por tenant/motivo.
     */
    public function escalation(int $tenantId, string $reason): void;

    /**
     * Observa o nº de round-trips de ferramenta por resposta (feature 017, alvo ≤ 3).
     */
    public function toolRoundTrips(int $tenantId, int $count): void;

    // -------------------------------------------------------------------------
    // Feature 018 — Coalescência (US1)
    // -------------------------------------------------------------------------

    public function coalesceMessagesPerTurn(int $tenantId, int $count): void;

    public function coalesceReprocessCount(int $tenantId, int $count): void;

    public function coalesceFlush(int $tenantId, string $reason): void;

    // -------------------------------------------------------------------------
    // Feature 018 — Servidor MCP (US7)
    // -------------------------------------------------------------------------

    public function mcpRequestDuration(string $capability, string $outcome, string $source, float $seconds): void;

    public function mcpCircuitState(int $state): void;

    public function mcpCircuitTransition(string $to, string $source): void;

    // -------------------------------------------------------------------------
    // Feature 018 — Áudio (US4 + US5)
    // -------------------------------------------------------------------------

    /** STT — latência por provedor e outcome (`success`|`error`). */
    public function sttDuration(string $provider, string $outcome, float $seconds): void;

    /** TTS — latência por provedor e outcome. */
    public function ttsDuration(string $provider, string $outcome, float $seconds): void;

    /** TTS — total de fallbacks para texto sob falha (FR-034). */
    public function ttsFallbackToText(string $provider, string $reason): void;

    // -------------------------------------------------------------------------
    // Feature 018 — Rate limit (Polish T200-T207)
    // -------------------------------------------------------------------------

    /** Conversa entrou em cooldown (FR-008b). `layer` ∈ {`per-conversation`, `per-identifier`}. */
    public function rateLimitCooldownActivated(int $tenantId, string $layer): void;

    // -------------------------------------------------------------------------
    // Feature 018 — Kanban (US3)
    // -------------------------------------------------------------------------

    /** Auto-curadoria aplicada/suprimida no card. `source` ∈ {`ia_tool`, `auto_listener`, `manual_override_blocked`}. */
    public function kanbanCurationEvent(int $tenantId, string $source, string $eventKind): void;
}
