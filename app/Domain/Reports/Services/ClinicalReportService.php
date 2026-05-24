<?php

declare(strict_types=1);

namespace App\Domain\Reports\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * **T274 (Fase 8 — Lote E US-10.3)** — Métricas clínicas.
 *
 * KPIs (AC-10.3.1):
 *   1. Ocupação por profissional (slots ocupados / disponíveis)
 *   2. Ranking de tipos de procedimento (volume + faturamento)
 *   3. Retornos completados vs perdidos (gated por feature flag — Fase 6
 *      Retornos não entregue ainda; service degrada gracioso quando
 *      `return_cadences` ausente)
 *
 * Escopo por perfil (Q13) — Médico vê apenas dados onde é `professional_id`;
 * Admin Clínica vê tenant inteiro.
 */
final class ClinicalReportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Tenant $tenant, Carbon $start, Carbon $end, ?User $user = null): array
    {
        $professionalFilter = $this->professionalScopeFor($user);

        $returns = $this->returnsStats($tenant->id, $start, $end);

        return [
            'period' => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String()],
            'scope' => $professionalFilter === null ? 'tenant' : 'own_agenda',
            'scoped_professional_id' => $professionalFilter,
            'occupancy_by_professional' => $this->occupancyByProfessional($tenant->id, $start, $end, $professionalFilter),
            'top_procedure_types' => $this->topProcedureTypes($tenant->id, $start, $end, $professionalFilter),
            'returns_stats' => ($returns['enabled'] ?? false) ? $returns : null,
        ];
    }

    /**
     * @return array{enabled: bool, completed?: int, missed?: int, completion_rate_percent?: float|null}
     */
    private function returnsStats(int $tenantId, Carbon $start, Carbon $end): array
    {
        if (! DB::getSchemaBuilder()->hasTable('return_cadences')) {
            return [
                'enabled' => false,
                'message' => 'Módulo de Retornos disponível em fase futura — habilite via feature flag quando entregue.',
            ];
        }

        $completed = (int) DB::table('return_cadences')
            ->where('tenant_id', $tenantId)
            ->whereBetween('completed_at', [$start, $end])
            ->whereNotNull('completed_at')
            ->count();

        $missed = (int) DB::table('return_cadences')
            ->where('tenant_id', $tenantId)
            ->whereBetween('due_at', [$start, $end])
            ->whereNull('completed_at')
            ->where('due_at', '<', Carbon::now())
            ->count();

        $total = $completed + $missed;
        $rate = $total === 0 ? 0.0 : round(($completed / $total) * 100, 2);

        return [
            'enabled' => true,
            'completed' => $completed,
            'missed' => $missed,
            'completion_rate_percent' => $rate,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function occupancyByProfessional(int $tenantId, Carbon $start, Carbon $end, ?int $professionalFilter): array
    {
        if (! DB::getSchemaBuilder()->hasTable('appointments')) {
            return [];
        }

        $query = DB::table('appointments')
            ->where('tenant_id', $tenantId)
            ->whereBetween('starts_at', [$start, $end])
            ->whereIn('status', ['scheduled', 'confirmed', 'realizada'])
            ->selectRaw('professional_id, COUNT(*) as total, SUM(CASE WHEN status=\'realizada\' THEN 1 ELSE 0 END) as realizadas')
            ->groupBy('professional_id');

        if ($professionalFilter !== null) {
            $query->where('professional_id', $professionalFilter);
        }

        return $query->get()->map(fn ($r): array => [
            'professional_id' => (int) $r->professional_id,
            'total_slots_used' => (int) $r->total,
            'realizadas' => (int) $r->realizadas,
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topProcedureTypes(int $tenantId, Carbon $start, Carbon $end, ?int $professionalFilter): array
    {
        if (! DB::getSchemaBuilder()->hasTable('appointments') || ! DB::getSchemaBuilder()->hasTable('appointment_types')) {
            return [];
        }

        $query = DB::table('appointments')
            ->where('appointments.tenant_id', $tenantId)
            ->whereBetween('starts_at', [$start, $end])
            ->where('status', 'realizada')
            ->join('appointment_types', 'appointments.appointment_type_id', '=', 'appointment_types.id')
            ->selectRaw('appointment_types.id, appointment_types.nome as name, COUNT(*) as total, SUM(appointment_types.valor_particular * 100) as revenue_cents')
            ->groupBy('appointment_types.id', 'appointment_types.nome')
            ->orderByDesc('total')
            ->limit(10);

        if ($professionalFilter !== null) {
            $query->where('appointments.professional_id', $professionalFilter);
        }

        return $query->get()->map(fn ($r): array => [
            'appointment_type_id' => (int) $r->id,
            'name' => $r->name,
            'volume' => (int) $r->total,
            'revenue_cents' => (int) $r->revenue_cents,
        ])->all();
    }

    /**
     * Q13 — Médico vê apenas dados onde é professional_id; demais perfis
     * veem dados completos do tenant.
     */
    private function professionalScopeFor(?User $user): ?int
    {
        if ($user === null) {
            return null;
        }

        if ($user->hasRole('medico') && ! $user->hasRole('admin-clinica')) {
            return $user->id;
        }

        return null;
    }
}
