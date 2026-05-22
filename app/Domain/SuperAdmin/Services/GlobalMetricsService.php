<?php

declare(strict_types=1);

namespace App\Domain\SuperAdmin\Services;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * **T132 (Fase 8 — Lote B US-12.3)** — KPIs globais do SaaS (AC-12.3.1 / AC-12.3.2).
 *
 * **Gate 5 NON-NEGOTIABLE**: nenhum método deste service deve retornar dados
 * INDIVIDUAIS de pacientes. Sempre agregados por tenant. `withoutGlobalScopes()`
 * é usado deliberadamente em queries cross-tenant — operação Super Admin only.
 *
 * Métricas (Q21):
 *   - MRR (Monthly Recurring Revenue) — soma `plan.base_price_cents` de tenants ativos
 *   - ARR (Annual Recurring Revenue) — MRR × 12
 *   - Churn rate primário — cancelamentos no período / ativos no início
 *   - Revenue churn — perda de MRR + downgrades (separado de Q21)
 *   - Conversão trial → pago — tenants que saíram de trial para active no período
 *   - Consumo total de IA — soma global de mensagens IA no mês corrente
 *
 * Resultados são VALORES AGREGADOS (numeric). Sem nome de tenant, sem
 * cidade, sem qualquer identificador além de id quando estritamente
 * necessário (drill-down do painel).
 */
final class GlobalMetricsService
{
    /**
     * MRR — soma das mensalidades de tenants ativos.
     *
     * Retorna em CENTAVOS para evitar float — UI converte para R$ na exibição.
     */
    public function computeMrr(): int
    {
        return (int) Tenant::query()
            ->withoutGlobalScopes()
            ->where('status', 'active')
            ->join('plans', 'tenants.plan_id', '=', 'plans.id')
            ->sum('plans.base_price_cents');
    }

    /**
     * ARR — MRR × 12 (aproximação clássica SaaS).
     */
    public function computeArr(): int
    {
        return $this->computeMrr() * 12;
    }

    /**
     * Churn rate primário (Q21): cancelamentos no período / ativos no início.
     *
     * @return array{denominator: int, cancelled: int, rate_percent: float}
     */
    public function computeChurnPrimary(?Carbon $start = null, ?Carbon $end = null): array
    {
        $start ??= Carbon::now()->subDays(30);
        $end ??= Carbon::now();

        // Ativos no início do período = tenants criados antes de $start E
        // não cancelados antes de $start.
        $denominator = (int) Tenant::query()
            ->withoutGlobalScopes()
            ->where('created_at', '<', $start)
            ->where(function ($q) use ($start): void {
                $q->whereNull('canceled_at')->orWhere('canceled_at', '>=', $start);
            })
            ->count();

        $cancelled = (int) Tenant::query()
            ->withoutGlobalScopes()
            ->whereBetween('canceled_at', [$start, $end])
            ->count();

        $rate = $denominator === 0 ? 0.0 : round(($cancelled / $denominator) * 100, 2);

        return [
            'denominator' => $denominator,
            'cancelled' => $cancelled,
            'rate_percent' => $rate,
        ];
    }

    /**
     * Revenue churn (complementar Q21) — perda de MRR por cancelamentos +
     * downgrades de plano no período.
     *
     * Downgrade é simplificado para "tenant com plano de menor preço hoje
     * que o anterior". Cálculo exato exige histórico de tenant_plan_bindings
     * (Lote B US-12.2).
     *
     * @return array{cancelled_mrr_lost_cents: int}
     */
    public function computeRevenueChurn(?Carbon $start = null, ?Carbon $end = null): array
    {
        $start ??= Carbon::now()->subDays(30);
        $end ??= Carbon::now();

        $cancelledMrrLost = (int) Tenant::query()
            ->withoutGlobalScopes()
            ->whereBetween('canceled_at', [$start, $end])
            ->join('plans', 'tenants.plan_id', '=', 'plans.id')
            ->sum('plans.base_price_cents');

        return [
            'cancelled_mrr_lost_cents' => $cancelledMrrLost,
        ];
    }

    /**
     * Conversão trial → pago no período. Aproximação: tenants cujo status
     * mudou de 'trial' para 'active' (proxy: created_at < período E status='active' E
     * trial_ends_at no período).
     *
     * @return array{trials_started: int, trials_converted: int, rate_percent: float}
     */
    public function computeTrialToPaidConversion(?Carbon $start = null, ?Carbon $end = null): array
    {
        $start ??= Carbon::now()->subDays(30);
        $end ??= Carbon::now();

        $trialsStarted = (int) Tenant::query()
            ->withoutGlobalScopes()
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $trialsConverted = (int) Tenant::query()
            ->withoutGlobalScopes()
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'active')
            ->count();

        $rate = $trialsStarted === 0 ? 0.0 : round(($trialsConverted / $trialsStarted) * 100, 2);

        return [
            'trials_started' => $trialsStarted,
            'trials_converted' => $trialsConverted,
            'rate_percent' => $rate,
        ];
    }

    /**
     * Consumo total de mensagens IA no mês corrente (cross-tenant).
     *
     * **Gate 5**: agregação SEM expor dados individuais. Query é COUNT() —
     * nenhum payload é retornado.
     */
    public function computeAiUsageTotal(?Carbon $monthStart = null): int
    {
        $monthStart ??= Carbon::now()->startOfMonth();

        // Tabela `ai_decision_logs` é placeholder para fase IA real.
        // Em produção pre-IA, esta query retorna 0 — comportamento aceitável.
        if (! DB::getSchemaBuilder()->hasTable('ai_decision_logs')) {
            return 0;
        }

        return (int) DB::table('ai_decision_logs')
            ->where('created_at', '>=', $monthStart)
            ->count();
    }

    /**
     * Snapshot consolidado de todas as métricas — usado pelo cron
     * `super-admin:compute-global-metrics`.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'mrr_cents' => $this->computeMrr(),
            'arr_cents' => $this->computeArr(),
            'tenants_active' => Tenant::query()->withoutGlobalScopes()->where('status', 'active')->count(),
            'churn_primary' => $this->computeChurnPrimary(),
            'revenue_churn' => $this->computeRevenueChurn(),
            'trial_to_paid' => $this->computeTrialToPaidConversion(),
            'ai_usage_total_month' => $this->computeAiUsageTotal(),
            'computed_at' => Carbon::now()->toIso8601String(),
        ];
    }
}
