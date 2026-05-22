<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Services;

use App\Domain\Privacy\Events\PoliticaPseudonimizacaoAuditada;
use App\Domain\Privacy\Models\PseudonymizationAudit;
use App\Domain\Privacy\Models\PseudonymizationAuditMode;
use App\Models\AuditLog;
use App\Support\Lgpd\ContainsNoClinicalData;
use App\Support\Lgpd\PiiDetector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use ReflectionClass;

/**
 * **T072 (Fase 8 — Lote A US-13.3)** — Coração da auditoria dual de pseudonimização (Q29).
 *
 * Combina duas estratégias defensivas:
 *
 * 1. **{@see runStaticReflection()}** — varre cada evento listado em
 *    `config('finalization.ai_consumed_events')` e valida via PHP Reflection
 *    que implementa {@see ContainsNoClinicalData}. Espelha o gate CI de T012
 *    mas é invocável em runtime (botão "Auditar agora" no painel).
 *
 * 2. **{@see runRuntimeReplay()}** — amostra randomicamente N% dos audit_logs
 *    persistidos e aplica {@see PiiDetector} sobre o `payload` JSONB.
 *    Detecta PII que escapou em runtime (ex.: dev esqueceu de mascarar
 *    novo campo, evento sem marker que foi adicionado depois do gate CI).
 *
 * Defesa em profundidade: estática previne em design-time, runtime valida
 * em produção. Findings críticos geram alertas Sentry.
 *
 * @see specs/008-finalizacao-mvp/research.md §1 Q29
 */
final class PseudonymizationAuditor
{
    public function __construct(
        private readonly int $defaultSamplePercent = 1,
    ) {}

    /**
     * Executa varredura ESTÁTICA via reflection sobre os eventos listados
     * em `config('finalization.ai_consumed_events')`.
     *
     * Findings = eventos que NÃO implementam {@see ContainsNoClinicalData}.
     */
    public function runStaticReflection(?int $auditedByUserId = null): PseudonymizationAudit
    {
        $eventClasses = (array) config('finalization.ai_consumed_events', []);
        $findings = [];

        foreach ($eventClasses as $eventClass) {
            if (! is_string($eventClass) || ! class_exists($eventClass)) {
                $findings[] = [
                    'event_class' => $eventClass,
                    'severity' => 'critical',
                    'reason' => 'class_not_found',
                ];

                continue;
            }

            $reflection = new ReflectionClass($eventClass);

            if (! $reflection->implementsInterface(ContainsNoClinicalData::class)) {
                $findings[] = [
                    'event_class' => $eventClass,
                    'severity' => 'critical',
                    'reason' => 'missing_marker_interface',
                ];
            }
        }

        $audit = PseudonymizationAudit::query()->create([
            'audited_at' => Carbon::now(),
            'audited_by_user_id' => $auditedByUserId,
            'mode' => PseudonymizationAuditMode::StaticReflection,
            'scope_event_types' => $eventClasses,
            'sample_size' => null,
            'total_events_scanned' => count($eventClasses),
            'non_conformant_events' => count($findings),
            'findings' => $findings === [] ? null : $findings,
            'report_summary' => $this->buildStaticSummary($eventClasses, $findings),
        ]);

        $this->emitAuditEvent($audit);

        return $audit;
    }

    /**
     * Executa REPLAY sobre amostra de audit_logs.
     *
     * Estratégia (research §3.3):
     *   1. Coleta últimos 7 dias de `audit_logs.action LIKE` (eventos materiais).
     *   2. Sample randômico de `$samplePercent`% (default 1%).
     *   3. Aplica `PiiDetector::detectInPayload()` em cada `payload`.
     *   4. Coleta findings sem armazenar valores reais.
     */
    public function runRuntimeReplay(?int $samplePercent = null, ?int $auditedByUserId = null): PseudonymizationAudit
    {
        $percent = $samplePercent ?? $this->defaultSamplePercent;
        $sevenDaysAgo = Carbon::now()->subDays(7);

        // Coleta universo de audit_logs nos últimos 7 dias.
        $totalUniverse = AuditLog::query()
            ->where('created_at', '>=', $sevenDaysAgo)
            ->count();

        $sampleSize = max(1, (int) ceil($totalUniverse * $percent / 100));

        $sampled = AuditLog::query()
            ->where('created_at', '>=', $sevenDaysAgo)
            ->inRandomOrder()
            ->limit($sampleSize)
            ->get(['id', 'action', 'payload']);

        $findings = [];
        $scope = [];

        foreach ($sampled as $log) {
            $action = $log->action;
            if (! in_array($action, $scope, true)) {
                $scope[] = $action;
            }

            $payload = is_array($log->payload) ? $log->payload : [];
            $hits = PiiDetector::detectInPayload($payload);

            foreach ($hits as $hit) {
                $findings[] = [
                    'audit_log_id' => $log->id,
                    'action' => $action,
                    'field_path' => $hit['field_path'],
                    'pattern' => $hit['pattern'],
                    'severity' => 'critical',
                ];
            }
        }

        $audit = PseudonymizationAudit::query()->create([
            'audited_at' => Carbon::now(),
            'audited_by_user_id' => $auditedByUserId,
            'mode' => PseudonymizationAuditMode::RuntimeReplay,
            'scope_event_types' => $scope,
            'sample_size' => $sampled->count(),
            'total_events_scanned' => $sampled->count(),
            'non_conformant_events' => count($findings),
            'findings' => $findings === [] ? null : $findings,
            'report_summary' => $this->buildReplaySummary($sampled->count(), $totalUniverse, $findings),
        ]);

        if ($findings !== []) {
            Log::critical('privacy.pseudonymization.findings_detected', [
                'audit_id' => $audit->id,
                'findings_count' => count($findings),
                'sample_size' => $sampled->count(),
                'sample_percent' => $percent,
            ]);
        }

        $this->emitAuditEvent($audit);

        return $audit;
    }

    private function emitAuditEvent(PseudonymizationAudit $audit): void
    {
        Event::dispatch(new PoliticaPseudonimizacaoAuditada(
            auditId: $audit->id,
            auditedAt: $audit->audited_at,
            mode: $audit->mode,
            totalEventsScanned: $audit->total_events_scanned,
            nonConformantEvents: $audit->non_conformant_events,
            auditedByUserId: $audit->audited_by_user_id,
        ));
    }

    /**
     * @param  list<string>                      $eventClasses
     * @param  list<array<string, mixed>>        $findings
     */
    private function buildStaticSummary(array $eventClasses, array $findings): string
    {
        $count = count($eventClasses);
        $issues = count($findings);
        $status = $issues === 0 ? 'COMPLIANT' : 'NON_COMPLIANT';

        return "**Static Reflection** — {$status}\n\n"
            ."- Eventos auditados: {$count}\n"
            ."- Não conformes: {$issues}";
    }

    /**
     * @param  list<array<string, mixed>>        $findings
     */
    private function buildReplaySummary(int $sample, int $universe, array $findings): string
    {
        $status = $findings === [] ? 'COMPLIANT' : 'NON_COMPLIANT';

        return "**Runtime Replay** — {$status}\n\n"
            ."- Universo (últimos 7d): {$universe}\n"
            ."- Amostra: {$sample}\n"
            ."- Findings: ".count($findings);
    }
}
