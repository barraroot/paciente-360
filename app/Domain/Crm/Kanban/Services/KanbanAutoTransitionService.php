<?php

declare(strict_types=1);

namespace App\Domain\Crm\Kanban\Services;

use App\Domain\Crm\Kanban\Events\KanbanCardCurated;
use App\Domain\Crm\Kanban\Models\KanbanCurationEvent;
use App\Models\FunilColuna;
use App\Models\Paciente;
use App\Support\Metrics\AiMetricsContract;

/**
 * **T100 (Fase 18 — US3, FR-018/019/020/023)** — aplica transições
 * automáticas de status no kanban a partir de eventos de domínio.
 *
 * Regras:
 *  - Mapping `event_kind → funil_coluna_id` vem de `KanbanPipelineMappingService`.
 *  - **FR-020 (manual override wins)**: se o paciente está em coluna mais
 *    "avançada" (posicao > target) OU foi movido manualmente, a transição
 *    automática que **regrediria** é SUPRIMIDA — registra
 *    `applied=false, suppression_reason='manual_override'` ou `'terminal_no_regress'`.
 *  - **FR-023**: a IA NÃO pode chamar este service para mover card para
 *    'agendado'/'confirmado' — esses estados refletem eventos de domínio
 *    reais (SlotReservation::created holder=ia, ConsultaConfirmada). Só
 *    os listeners (T101-T104) os disparam. Capability MCP não tem acesso.
 *
 * Heurística "avancou" usa `posicao` da coluna (todas as default columns têm
 * posicao crescente — `new` < `qualificando` < ... < `confirmado`).
 */
final class KanbanAutoTransitionService
{
    public function __construct(
        private readonly KanbanPipelineMappingService $mappings,
        private readonly AiMetricsContract $metrics,
    ) {}

    /**
     * Aplica a transição para `event_kind`. Retorna o `KanbanCurationEvent`
     * emitido (com `applied=false` se suprimido — FR-020).
     *
     * @param array<string, mixed> $context contexto extra para audit (reason, etc.)
     */
    public function apply(Paciente $paciente, string $eventKind, array $context = []): KanbanCurationEvent
    {
        $target = $this->mappings->colunaFor($paciente->tenant_id, $eventKind);

        // Mapping não configurado → no-op auditado.
        if ($target === null) {
            return $this->emitSuppressed(
                $paciente,
                $eventKind,
                'mapping_not_configured',
                null,
                $context,
            );
        }

        $current = $paciente->funil_coluna_atual_id !== null
            ? FunilColuna::query()
                ->where('tenant_id', $paciente->tenant_id)
                ->find($paciente->funil_coluna_atual_id)
            : null;

        // FR-020 — não regredir: se current.posicao >= target.posicao, suprime.
        if ($current !== null && $current->posicao >= $target->posicao && $current->id !== $target->id) {
            return $this->emitSuppressed(
                $paciente,
                $eventKind,
                'manual_override',
                $current->id,
                $context,
                $target->id,
            );
        }

        $fromColunaId = $paciente->funil_coluna_atual_id;
        $paciente->update(['funil_coluna_atual_id' => $target->id]);

        $event = KanbanCurationEvent::create([
            'tenant_id' => $paciente->tenant_id,
            'paciente_id' => $paciente->id,
            'event_kind' => $eventKind,
            'source' => 'auto_listener',
            'from_coluna_id' => $fromColunaId,
            'to_coluna_id' => $target->id,
            'applied' => true,
            'suppression_reason' => null,
            'actor_type' => 'system',
            'reason' => (string) ($context['reason'] ?? "Transição automática por evento {$eventKind}"),
            'created_at' => now(),
        ]);

        event(new KanbanCardCurated($event));

        $this->metrics->kanbanCurationEvent(
            tenantId: $paciente->tenant_id,
            source: 'auto_listener',
            eventKind: $eventKind,
        );

        return $event;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function emitSuppressed(
        Paciente $paciente,
        string $eventKind,
        string $reason,
        ?int $fromColunaId,
        array $context,
        ?int $intendedToColunaId = null,
    ): KanbanCurationEvent {
        $event = KanbanCurationEvent::create([
            'tenant_id' => $paciente->tenant_id,
            'paciente_id' => $paciente->id,
            'event_kind' => $eventKind,
            'source' => 'manual_override_blocked',
            'from_coluna_id' => $fromColunaId,
            'to_coluna_id' => $intendedToColunaId,
            'applied' => false,
            'suppression_reason' => $reason,
            'actor_type' => 'system',
            'reason' => "Transição automática suprimida ({$reason}). "
                .(string) ($context['reason'] ?? "Evento {$eventKind} ignorado."),
            'created_at' => now(),
        ]);

        event(new KanbanCardCurated($event));

        $this->metrics->kanbanCurationEvent(
            tenantId: $paciente->tenant_id,
            source: 'manual_override_blocked',
            eventKind: $eventKind,
        );

        return $event;
    }
}
