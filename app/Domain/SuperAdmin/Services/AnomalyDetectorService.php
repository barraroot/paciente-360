<?php

declare(strict_types=1);

namespace App\Domain\SuperAdmin\Services;

use App\Domain\SuperAdmin\Events\AnomaliaDetectada;
use App\Domain\SuperAdmin\Models\AnomalyCategory;
use App\Domain\SuperAdmin\Models\AnomalyDetected;
use App\Domain\SuperAdmin\Models\AnomalySeverity;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * **T133 (Fase 8 — Lote B US-12.3)** — Detector de anomalias (Q22 — 4 categorias).
 *
 * Thresholds em `config('finalization.anomaly_thresholds')`:
 *   - conversion_drop_percent: 20.0  (queda > 20% WoW)
 *   - ai_usage_spike_multiplier: 10.0 (> 10× média histórica do tenant)
 *   - webhook_failure_rate_percent: 50.0 (> 50% em 1h, vol mínimo 10)
 *   - payment_overdue_days_critical: 30 (inadimplência > 30 dias)
 *
 * **Cooldown** (Q22) — `anomaly_alert_cooldown_minutes` default 30min entre
 * alertas da mesma categoria + mesmo tenant para evitar flood.
 *
 * Cada anomalia detectada cria row em `anomalies_detected` + emite
 * `AnomaliaDetectada` (que aciona `NotifyAnomalyToSuperAdminListener`).
 */
final class AnomalyDetectorService
{
    /**
     * Roda todos os 4 detectores. Retorna lista de anomalias detectadas neste
     * ciclo.
     *
     * @return list<AnomalyDetected>
     */
    public function detectAll(): array
    {
        $detected = [];

        $detected = array_merge($detected, $this->detectConversionDrop());
        $detected = array_merge($detected, $this->detectAiUsageSpike());
        $detected = array_merge($detected, $this->detectWebhookFailureRate());
        $detected = array_merge($detected, $this->detectPaymentOverdue());

        return $detected;
    }

    /**
     * Conversion trial → pago caiu > X% WoW (semana corrente vs anterior).
     * Anomalia global (tenant_id = NULL).
     *
     * @return list<AnomalyDetected>
     */
    public function detectConversionDrop(): array
    {
        $threshold = (float) config('finalization.anomaly_thresholds.conversion_drop_percent', 20.0);

        $now = Carbon::now();
        $thisWeekStart = $now->copy()->subDays(7);
        $previousWeekStart = $now->copy()->subDays(14);

        $thisWeekStarted = Tenant::query()->withoutGlobalScopes()
            ->whereBetween('created_at', [$thisWeekStart, $now])
            ->count();
        $thisWeekConverted = Tenant::query()->withoutGlobalScopes()
            ->whereBetween('created_at', [$thisWeekStart, $now])
            ->where('status', 'active')
            ->count();

        $previousWeekStarted = Tenant::query()->withoutGlobalScopes()
            ->whereBetween('created_at', [$previousWeekStart, $thisWeekStart])
            ->count();
        $previousWeekConverted = Tenant::query()->withoutGlobalScopes()
            ->whereBetween('created_at', [$previousWeekStart, $thisWeekStart])
            ->where('status', 'active')
            ->count();

        $thisRate = $thisWeekStarted === 0 ? 0 : ($thisWeekConverted / $thisWeekStarted) * 100;
        $prevRate = $previousWeekStarted === 0 ? 0 : ($previousWeekConverted / $previousWeekStarted) * 100;

        if ($prevRate <= 0) {
            return [];
        }

        $dropPercent = (($prevRate - $thisRate) / $prevRate) * 100;
        if ($dropPercent < $threshold) {
            return [];
        }

        return $this->record(
            categoria: AnomalyCategory::ConversionDrop,
            tenantId: null,
            severity: AnomalySeverity::Critical,
            thresholdBreached: [
                'metric' => 'conversion_drop_percent_wow',
                'threshold' => $threshold,
                'observed_value' => round($dropPercent, 2),
                'this_week_rate' => round($thisRate, 2),
                'previous_week_rate' => round($prevRate, 2),
            ],
        );
    }

    /**
     * Tenant com consumo de IA > X× a média histórica do próprio tenant.
     *
     * @return list<AnomalyDetected>
     */
    public function detectAiUsageSpike(): array
    {
        $multiplier = (float) config('finalization.anomaly_thresholds.ai_usage_spike_multiplier', 10.0);

        if (! DB::getSchemaBuilder()->hasTable('ai_decision_logs')) {
            return []; // Sem tabela = sem detecção (fase IA não entregue ainda).
        }

        $detected = [];

        Tenant::query()->withoutGlobalScopes()->where('status', 'active')->chunk(50, function ($tenants) use ($multiplier, &$detected): void {
            foreach ($tenants as $tenant) {
                $thisMonth = (int) DB::table('ai_decision_logs')
                    ->where('tenant_id', $tenant->id)
                    ->where('created_at', '>=', Carbon::now()->startOfMonth())
                    ->count();

                $historicalAvg = (int) DB::table('ai_decision_logs')
                    ->where('tenant_id', $tenant->id)
                    ->where('created_at', '<', Carbon::now()->startOfMonth())
                    ->where('created_at', '>=', Carbon::now()->subMonths(6)->startOfMonth())
                    ->count() / 6;

                if ($historicalAvg < 10) {
                    continue; // volume muito baixo para detectar spike confiável
                }

                if ($thisMonth >= ($historicalAvg * $multiplier)) {
                    $detected = array_merge($detected, $this->record(
                        categoria: AnomalyCategory::AiUsageSpike,
                        tenantId: $tenant->id,
                        severity: AnomalySeverity::Critical,
                        thresholdBreached: [
                            'metric' => 'ai_messages_month_vs_historical_avg',
                            'threshold_multiplier' => $multiplier,
                            'observed_value' => $thisMonth,
                            'historical_avg' => round($historicalAvg, 2),
                        ],
                    ));
                }
            }
        });

        return $detected;
    }

    /**
     * Taxa de falha de webhook > X% em 1h por tenant (vol mínimo 10 entregas).
     *
     * @return list<AnomalyDetected>
     */
    public function detectWebhookFailureRate(): array
    {
        $threshold = (float) config('finalization.anomaly_thresholds.webhook_failure_rate_percent', 50.0);

        if (! DB::getSchemaBuilder()->hasTable('webhook_deliveries')) {
            return []; // Sem tabela = Lote D ainda não entregue.
        }

        $oneHourAgo = Carbon::now()->subHour();
        $detected = [];

        $stats = DB::table('webhook_deliveries')
            ->select('tenant_id', DB::raw('COUNT(*) as total'), DB::raw("SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed"))
            ->where('created_at', '>=', $oneHourAgo)
            ->groupBy('tenant_id')
            // Postgres não aceita alias de SELECT em HAVING — repete a expressão.
            ->havingRaw('COUNT(*) >= 10')
            ->get();

        foreach ($stats as $row) {
            $failureRate = ((int) $row->failed / (int) $row->total) * 100;
            if ($failureRate < $threshold) {
                continue;
            }

            $detected = array_merge($detected, $this->record(
                categoria: AnomalyCategory::WebhookFailureRate,
                tenantId: (int) $row->tenant_id,
                severity: AnomalySeverity::Warning,
                thresholdBreached: [
                    'metric' => 'webhook_failure_rate_1h',
                    'threshold' => $threshold,
                    'observed_value' => round($failureRate, 2),
                    'total_deliveries' => (int) $row->total,
                    'failed_deliveries' => (int) $row->failed,
                ],
            ));
        }

        return $detected;
    }

    /**
     * Tenants inadimplentes há > X dias (default 30 = critical).
     *
     * @return list<AnomalyDetected>
     */
    public function detectPaymentOverdue(): array
    {
        $criticalDays = (int) config('finalization.anomaly_thresholds.payment_overdue_days_critical', 30);

        $cutoff = Carbon::now()->subDays($criticalDays);

        $tenants = Tenant::query()->withoutGlobalScopes()
            ->where('status', 'overdue')
            ->where('overdue_since', '<=', $cutoff)
            ->get(['id', 'overdue_since']);

        $detected = [];
        foreach ($tenants as $tenant) {
            $daysOverdue = (int) $tenant->overdue_since->diffInDays(Carbon::now());

            $detected = array_merge($detected, $this->record(
                categoria: AnomalyCategory::PaymentOverdue,
                tenantId: $tenant->id,
                severity: $daysOverdue >= 60 ? AnomalySeverity::Critical : AnomalySeverity::Warning,
                thresholdBreached: [
                    'metric' => 'payment_overdue_days',
                    'threshold' => $criticalDays,
                    'observed_value' => $daysOverdue,
                ],
            ));
        }

        return $detected;
    }

    /**
     * Cria row + dispara evento, respeitando cooldown.
     *
     * @param array<string, mixed> $thresholdBreached
     * @return list<AnomalyDetected> array vazio se cooldown ativo, ou [anomaly]
     */
    private function record(
        AnomalyCategory $categoria,
        ?int $tenantId,
        AnomalySeverity $severity,
        array $thresholdBreached,
    ): array {
        $cooldown = (int) config('finalization.anomaly_alert_cooldown_minutes', 30);

        $inCooldown = AnomalyDetected::query()
            ->withinCooldown($categoria, $tenantId, $cooldown)
            ->exists();

        if ($inCooldown) {
            return [];
        }

        $now = Carbon::now();
        $notifiedVia = $severity === AnomalySeverity::Critical ? ['inbox', 'email'] : ['inbox'];

        $anomaly = AnomalyDetected::query()->create([
            'categoria' => $categoria,
            'tenant_id' => $tenantId,
            'severity' => $severity,
            'threshold_breached' => $thresholdBreached,
            'detected_at' => $now,
            'notified_via' => $notifiedVia,
        ]);

        Event::dispatch(new AnomaliaDetectada(
            anomalyId: $anomaly->id,
            categoria: $categoria,
            tenantId: $tenantId,
            severity: $severity,
            thresholdBreached: $thresholdBreached,
            detectedAt: $now,
        ));

        return [$anomaly];
    }
}
