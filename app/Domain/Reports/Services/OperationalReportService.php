<?php

declare(strict_types=1);

namespace App\Domain\Reports\Services;

use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * **T268 (Fase 8 — Lote E US-10.2)** — Métricas operacionais de atendimento.
 *
 * KPIs (AC-10.2.1):
 *   1. Tempo médio de primeira resposta (humano + IA combinados)
 *   2. Tempo médio até resolução da conversa
 *   3. Volume por atendente
 *   4. Performance da IA (resolução autônoma + escalonamento + score)
 *
 * **Graceful degradation**: tabelas `conversations`/`messages`/`ai_decision_logs`
 * podem não existir em ambientes pre-Fase 3/4 — service retorna valores
 * neutros para não quebrar dashboard.
 */
final class OperationalReportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Tenant $tenant, Carbon $start, Carbon $end): array
    {
        return [
            'period' => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String()],
            'first_response_time' => $this->firstResponseTime($tenant->id, $start, $end),
            'resolution_time' => $this->resolutionTime($tenant->id, $start, $end),
            'volume_per_attendant' => $this->volumePerAttendant($tenant->id, $start, $end),
            'ai_performance' => $this->aiPerformance($tenant->id, $start, $end),
        ];
    }

    /**
     * @return array{p50: float|null, p95: float|null, avg: float|null}
     */
    private function firstResponseTime(int $tenantId, Carbon $start, Carbon $end): array
    {
        // Placeholder — Fase 3 messages não expõe first_response_at padronizado.
        // Slice futuro: cron messaging:compute-response-times agrega.
        return ['p50' => null, 'p95' => null, 'avg' => null];
    }

    /**
     * @return array{avg_minutes: float|null}
     */
    private function resolutionTime(int $tenantId, Carbon $start, Carbon $end): array
    {
        return ['avg_minutes' => null];
    }

    /**
     * @return array<int, array{user_id: int, messages_sent: int, conversations_handled: int}>
     */
    private function volumePerAttendant(int $tenantId, Carbon $start, Carbon $end): array
    {
        if (! DB::getSchemaBuilder()->hasTable('messages')) {
            return [];
        }

        return DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('user_id')
            // Feature 018 (T186, FR-042) — exclui tráfego de Persona Test Sessions.
            ->where(function ($q): void {
                $q->where('sandbox', false)->orWhereNull('sandbox');
            })
            ->selectRaw('user_id, COUNT(*) as messages_sent, COUNT(DISTINCT conversation_id) as conversations_handled')
            ->groupBy('user_id')
            ->orderByDesc('messages_sent')
            ->get()
            ->map(fn ($r): array => [
                'user_id' => (int) $r->user_id,
                'messages_sent' => (int) $r->messages_sent,
                'conversations_handled' => (int) $r->conversations_handled,
            ])
            ->all();
    }

    /**
     * @return array{total_decisions: int, autonomous_rate_percent: float, escalation_rate_percent: float, avg_confidence: float|null}
     */
    private function aiPerformance(int $tenantId, Carbon $start, Carbon $end): array
    {
        if (! DB::getSchemaBuilder()->hasTable('ai_decision_logs')) {
            return [
                'total_decisions' => 0,
                'autonomous_rate_percent' => 0.0,
                'escalation_rate_percent' => 0.0,
                'avg_confidence' => null,
            ];
        }

        $total = (int) DB::table('ai_decision_logs')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('decision_confidence')
            ->count();

        if ($total === 0) {
            return [
                'total_decisions' => 0,
                'autonomous_rate_percent' => 0.0,
                'escalation_rate_percent' => 0.0,
                'avg_confidence' => null,
            ];
        }

        $autonomous = (int) DB::table('ai_decision_logs')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('decision_confidence')
            ->where('escalated_to_human', false)
            ->count();

        $avgConfidence = (float) DB::table('ai_decision_logs')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('decision_confidence')
            ->avg('decision_confidence');

        return [
            'total_decisions' => $total,
            'autonomous_rate_percent' => round(($autonomous / $total) * 100, 2),
            'escalation_rate_percent' => round((($total - $autonomous) / $total) * 100, 2),
            'avg_confidence' => round($avgConfidence, 3),
        ];
    }
}
