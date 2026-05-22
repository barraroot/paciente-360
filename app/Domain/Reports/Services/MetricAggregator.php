<?php

declare(strict_types=1);

namespace App\Domain\Reports\Services;

use App\Domain\Reports\Models\MetricAggregation;
use App\Domain\Reports\Models\MetricPeriod;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * **T249 (Fase 8 — Lote E US-10.1)** — Pré-computa métricas agregadas (Q9).
 *
 * Roda via `AggregateHourlyMetricsCommand` hourly (T252) — atualiza
 * `metric_aggregations` para janelas ≥ 7 dias. Janelas ≤ 24h usam queries
 * on-demand no `ExecutiveDashboardService`.
 *
 * 8 métricas suportadas:
 *   1. leads_by_channel
 *   2. conversion_rate
 *   3. no_show_rate
 *   4. estimated_revenue
 *   5. response_time_first_p95
 *   6. ai_autonomous_resolution_rate
 *   7. occupancy_by_professional
 *   8. top_procedure_types
 *
 * **Idempotência**: UNIQUE composto `(tenant_id, metric_name, period, period_start, dimensions)`
 * garante upsert. Re-execução não duplica.
 */
final class MetricAggregator
{
    public const METRICS = [
        'leads_by_channel',
        'conversion_rate',
        'no_show_rate',
        'estimated_revenue',
        'response_time_first_p95',
        'ai_autonomous_resolution_rate',
        'occupancy_by_professional',
        'top_procedure_types',
    ];

    /**
     * Pré-computa todas as métricas para um tenant na janela do dia atual.
     */
    public function aggregateDailyForTenant(Tenant $tenant, ?Carbon $day = null): int
    {
        $day ??= Carbon::today();
        $count = 0;

        foreach (self::METRICS as $metric) {
            $value = $this->computeMetric($tenant->id, $metric, $day);
            if ($value === null) {
                continue;
            }

            $this->upsert(
                tenantId: $tenant->id,
                metricName: $metric,
                period: MetricPeriod::Day,
                periodStart: $day,
                value: $value,
            );
            $count++;
        }

        return $count;
    }

    /**
     * Calcula uma métrica específica para um período diário.
     *
     * @return array{numeric?: float|null, json?: array<string,mixed>|null}|null
     */
    public function computeMetric(int $tenantId, string $metric, Carbon $day): ?array
    {
        $dayStart = $day->copy()->startOfDay();
        $dayEnd = $day->copy()->endOfDay();

        return match ($metric) {
            'leads_by_channel' => $this->leadsByChannel($tenantId, $dayStart, $dayEnd),
            'conversion_rate' => $this->conversionRate($tenantId, $dayStart, $dayEnd),
            'no_show_rate' => $this->noShowRate($tenantId, $dayStart, $dayEnd),
            'estimated_revenue' => $this->estimatedRevenue($tenantId, $dayStart, $dayEnd),
            'response_time_first_p95' => $this->responseTimeP95($tenantId, $dayStart, $dayEnd),
            'ai_autonomous_resolution_rate' => $this->aiAutonomousResolutionRate($tenantId, $dayStart, $dayEnd),
            'occupancy_by_professional' => $this->occupancyByProfessional($tenantId, $dayStart, $dayEnd),
            'top_procedure_types' => $this->topProcedureTypes($tenantId, $dayStart, $dayEnd),
            default => null,
        };
    }

    /**
     * @return array{numeric?: float|null, json?: array<string,mixed>|null}
     */
    private function leadsByChannel(int $tenantId, Carbon $start, Carbon $end): array
    {
        if (! DB::getSchemaBuilder()->hasTable('pacientes')) {
            return ['numeric' => 0.0];
        }

        $byChannel = DB::table('pacientes')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('origem, COUNT(*) as total')
            ->groupBy('origem')
            ->pluck('total', 'origem')
            ->toArray();

        $total = (int) array_sum($byChannel);

        return [
            'numeric' => (float) $total,
            'json' => ['by_channel' => $byChannel, 'total' => $total],
        ];
    }

    /**
     * @return array{numeric: float|null}
     */
    private function conversionRate(int $tenantId, Carbon $start, Carbon $end): array
    {
        if (! DB::getSchemaBuilder()->hasTable('pacientes') || ! DB::getSchemaBuilder()->hasTable('appointments')) {
            return ['numeric' => 0.0];
        }

        $leads = (int) DB::table('pacientes')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $converted = (int) DB::table('appointments')
            ->where('tenant_id', $tenantId)
            ->whereBetween('starts_at', [$start, $end->copy()->addDays(30)])
            ->where('status', 'realizada')
            ->distinct('patient_id')
            ->count('patient_id');

        $rate = $leads === 0 ? 0.0 : round(($converted / $leads) * 100, 2);

        return ['numeric' => $rate];
    }

    /**
     * @return array{numeric: float|null}
     */
    private function noShowRate(int $tenantId, Carbon $start, Carbon $end): array
    {
        if (! DB::getSchemaBuilder()->hasTable('appointments')) {
            return ['numeric' => 0.0];
        }

        $total = (int) DB::table('appointments')
            ->where('tenant_id', $tenantId)
            ->whereBetween('starts_at', [$start, $end])
            ->whereIn('status', ['realizada', 'nao_realizada'])
            ->count();

        $noShow = (int) DB::table('appointments')
            ->where('tenant_id', $tenantId)
            ->whereBetween('starts_at', [$start, $end])
            ->where('status', 'nao_realizada')
            ->count();

        $rate = $total === 0 ? 0.0 : round(($noShow / $total) * 100, 2);

        return ['numeric' => $rate];
    }

    /**
     * @return array{numeric: float}
     */
    private function estimatedRevenue(int $tenantId, Carbon $start, Carbon $end): array
    {
        if (! DB::getSchemaBuilder()->hasTable('appointments') || ! DB::getSchemaBuilder()->hasTable('appointment_types')) {
            return ['numeric' => 0.0];
        }

        $totalCents = (int) DB::table('appointments')
            ->where('appointments.tenant_id', $tenantId)
            ->whereBetween('starts_at', [$start, $end])
            ->where('status', 'realizada')
            ->join('appointment_types', 'appointments.appointment_type_id', '=', 'appointment_types.id')
            ->sum('appointment_types.price_cents');

        return ['numeric' => (float) $totalCents];
    }

    /**
     * @return array{numeric: float|null}
     */
    private function responseTimeP95(int $tenantId, Carbon $start, Carbon $end): array
    {
        // Placeholder — Fase 3 messages.first_response_at não está padronizado;
        // implementação real fica para slice de polish da Fase 8.
        return ['numeric' => 0.0];
    }

    /**
     * @return array{numeric: float|null}
     */
    private function aiAutonomousResolutionRate(int $tenantId, Carbon $start, Carbon $end): array
    {
        if (! DB::getSchemaBuilder()->hasTable('ai_decision_logs')) {
            return ['numeric' => 0.0];
        }

        $total = (int) DB::table('ai_decision_logs')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $autonomous = (int) DB::table('ai_decision_logs')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->where('escalated_to_human', false)
            ->count();

        $rate = $total === 0 ? 0.0 : round(($autonomous / $total) * 100, 2);

        return ['numeric' => $rate];
    }

    /**
     * @return array{numeric: float, json: array{by_professional: array<int, mixed>}}
     */
    private function occupancyByProfessional(int $tenantId, Carbon $start, Carbon $end): array
    {
        if (! DB::getSchemaBuilder()->hasTable('appointments')) {
            return ['numeric' => 0.0, 'json' => ['by_professional' => []]];
        }

        $byProf = DB::table('appointments')
            ->where('tenant_id', $tenantId)
            ->whereBetween('starts_at', [$start, $end])
            ->whereIn('status', ['scheduled', 'confirmed', 'realizada'])
            ->selectRaw('professional_id, COUNT(*) as total')
            ->groupBy('professional_id')
            ->get()
            ->map(fn ($r): array => [
                'professional_id' => (int) $r->professional_id,
                'consultas' => (int) $r->total,
            ])
            ->all();

        $avg = $byProf === [] ? 0.0 : round(array_sum(array_column($byProf, 'consultas')) / count($byProf), 2);

        return ['numeric' => $avg, 'json' => ['by_professional' => $byProf]];
    }

    /**
     * @return array{numeric: float, json: array{top: array<int, mixed>}}
     */
    private function topProcedureTypes(int $tenantId, Carbon $start, Carbon $end): array
    {
        if (! DB::getSchemaBuilder()->hasTable('appointments') || ! DB::getSchemaBuilder()->hasTable('appointment_types')) {
            return ['numeric' => 0.0, 'json' => ['top' => []]];
        }

        $top = DB::table('appointments')
            ->where('appointments.tenant_id', $tenantId)
            ->whereBetween('starts_at', [$start, $end])
            ->where('status', 'realizada')
            ->join('appointment_types', 'appointments.appointment_type_id', '=', 'appointment_types.id')
            ->selectRaw('appointment_types.id, appointment_types.name, COUNT(*) as total')
            ->groupBy('appointment_types.id', 'appointment_types.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($r): array => [
                'appointment_type_id' => (int) $r->id,
                'name' => $r->name,
                'total' => (int) $r->total,
            ])
            ->all();

        return [
            'numeric' => (float) array_sum(array_column($top, 'total')),
            'json' => ['top' => $top],
        ];
    }

    /**
     * @param array{numeric?: float|null, json?: array<string,mixed>|null} $value
     */
    private function upsert(int $tenantId, string $metricName, MetricPeriod $period, Carbon $periodStart, array $value): void
    {
        MetricAggregation::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'metric_name' => $metricName,
                'period' => $period,
                'period_start' => $periodStart,
            ],
            [
                'dimensions' => null,
                'value_numeric' => $value['numeric'] ?? null,
                'value_json' => $value['json'] ?? null,
                'computed_at' => Carbon::now(),
            ],
        );
    }
}
