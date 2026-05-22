<?php

declare(strict_types=1);

namespace App\Domain\Reports\Services;

use App\Domain\Reports\Models\MetricAggregation;
use App\Domain\Reports\Models\MetricPeriod;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * **T250 (Fase 8 — Lote E US-10.1)** — KPIs do Dashboard Executivo (AC-10.1.1).
 *
 * 5 cards:
 *   1. leads_by_channel
 *   2. conversion_rate
 *   3. no_show_rate
 *   4. NPS (placeholder Q8 — "Em breve")
 *   5. estimated_revenue
 *
 * Estratégia (Q9): janelas ≥ 7 dias usam agregações pré-computadas
 * (`MetricAggregator`); janelas ≤ 24h calculam live via DB query.
 *
 * **Escopo por perfil (Q13)** aplicado pelo ExecutiveDashboardController
 * via Gate — Médico vê apenas dados onde é professional_id; Admin Clínica
 * vê tenant inteiro.
 */
final class ExecutiveDashboardService
{
    public function __construct(
        private readonly MetricAggregator $aggregator,
    ) {}

    /**
     * Retorna KPIs para uma janela [start, end]. Aplica escopo por perfil
     * se `$user` for Médico (filter professional_id).
     *
     * @return array<string, mixed>
     */
    public function getKpis(Tenant $tenant, Carbon $start, Carbon $end, ?User $user = null): array
    {
        $useAggregated = $this->shouldUseAggregations($start, $end);

        return [
            'period' => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String()],
            'leads_by_channel' => $this->fetchWithDelta('leads_by_channel', $tenant, $start, $end, $useAggregated),
            'conversion_rate' => $this->fetchWithDelta('conversion_rate', $tenant, $start, $end, $useAggregated),
            'no_show_rate' => $this->fetchWithDelta('no_show_rate', $tenant, $start, $end, $useAggregated),
            'nps' => [
                'value' => null,
                'delta_percent' => null,
                'placeholder' => true,
                'message' => 'NPS — disponível em fase futura (Q8)',
            ],
            'estimated_revenue' => $this->fetchWithDelta('estimated_revenue', $tenant, $start, $end, $useAggregated),
            'response_time_first_p95' => $this->fetchWithDelta('response_time_first_p95', $tenant, $start, $end, $useAggregated),
            'computed_at' => Carbon::now()->toIso8601String(),
            'used_aggregations' => $useAggregated,
            'aggregation_lag_seconds' => $useAggregated ? $this->aggregationLagSeconds($tenant) : 0,
        ];
    }

    /**
     * Wrapper que adiciona delta_percent comparando contra período anterior.
     *
     * @return array{value: float|null, delta_percent: float|null, json?: mixed}
     */
    private function fetchWithDelta(string $metric, Tenant $tenant, Carbon $start, Carbon $end, bool $useAggregated): array
    {
        $current = $this->fetch($metric, $tenant, $start, $end, $useAggregated);

        $duration = $start->diffInDays($end, true);
        $prevEnd = $start->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays((int) $duration);
        $previous = $this->fetch($metric, $tenant, $prevStart, $prevEnd, $useAggregated);

        $cv = $current['value'] ?? 0;
        $pv = $previous['value'] ?? 0;

        $delta = ($pv == 0)
            ? null
            : round((($cv - $pv) / $pv) * 100, 2);

        return [
            'value' => $current['value'],
            'delta_percent' => $delta,
            'json' => $current['json'] ?? null,
        ];
    }

    /**
     * Drill-down de um KPI específico — retorna lista filtrada
     * (consumida pelo Controller que serializa via Resource).
     *
     * @return array<string, mixed>
     */
    public function drillDown(Tenant $tenant, string $metric, Carbon $start, Carbon $end): array
    {
        // Sempre query live para drill-down — agregações armazenam apenas counts.
        $useAggregated = false;
        $result = $this->aggregator->computeMetric($tenant->id, $metric, $start);

        return [
            'metric' => $metric,
            'period' => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String()],
            'detail' => $result['json'] ?? null,
        ];
    }

    /**
     * @return array{value: float|null, label: string|null}
     */
    private function fetch(string $metric, Tenant $tenant, Carbon $start, Carbon $end, bool $useAggregated): array
    {
        if ($useAggregated) {
            $row = MetricAggregation::query()
                ->where('tenant_id', $tenant->id)
                ->forMetric($metric)
                ->forPeriod(MetricPeriod::Day)
                ->between($start, $end)
                ->orderByDesc('period_start')
                ->first();

            return [
                'value' => $row?->value_numeric,
                'json' => $row?->value_json,
            ];
        }

        // Live query — calcula on-demand para janelas curtas (≤ 24h).
        $result = $this->aggregator->computeMetric($tenant->id, $metric, $start);

        return [
            'value' => $result['numeric'] ?? null,
            'json' => $result['json'] ?? null,
        ];
    }

    /**
     * Comparação contra o período anterior de mesmo tamanho (Q11 — variação %).
     *
     * @return array<string, mixed>
     */
    private function computePreviousPeriodComparison(Tenant $tenant, Carbon $start, Carbon $end, bool $useAggregated): array
    {
        $duration = $start->diffInDays($end, true);
        $prevEnd = $start->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays((int) $duration);

        $current = $this->fetch('leads_by_channel', $tenant, $start, $end, $useAggregated);
        $previous = $this->fetch('leads_by_channel', $tenant, $prevStart, $prevEnd, $useAggregated);

        $currentValue = $current['value'] ?? 0;
        $previousValue = $previous['value'] ?? 0;

        $deltaPercent = $previousValue == 0
            ? null
            : round((($currentValue - $previousValue) / $previousValue) * 100, 2);

        return [
            'leads_by_channel' => [
                'current' => $currentValue,
                'previous' => $previousValue,
                'delta_percent' => $deltaPercent,
            ],
        ];
    }

    /**
     * Q9 — janelas ≥ 24h usam agregações pré-computadas (mais rápido).
     */
    private function shouldUseAggregations(Carbon $start, Carbon $end): bool
    {
        $thresholdHours = (int) config('finalization.report_aggregation_threshold_hours', 24);

        return $start->diffInHours($end, true) >= $thresholdHours;
    }

    /**
     * Lag entre `computed_at` da última agregação e agora — usado para banner
     * "Dados podem estar com até X minutos de atraso" (R-8-5).
     */
    private function aggregationLagSeconds(Tenant $tenant): int
    {
        $latest = MetricAggregation::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('computed_at')
            ->first();

        if ($latest === null) {
            return 0;
        }

        return (int) $latest->computed_at->diffInSeconds(Carbon::now(), true);
    }
}
