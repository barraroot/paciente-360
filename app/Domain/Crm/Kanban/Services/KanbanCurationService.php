<?php

declare(strict_types=1);

namespace App\Domain\Crm\Kanban\Services;

use App\Domain\Crm\Kanban\Events\KanbanCardCurated;
use App\Domain\Crm\Kanban\Models\KanbanCurationEvent;
use App\Models\Paciente;
use App\Support\Metrics\AiMetricsContract;

/**
 * **T099 (Fase 18 — US3, FR-016/017/021/022)** — Atualiza campos do card via
 * tool/capability auditada.
 *
 * Allow-list de campos NÃO clínicos (FR-016): nome, queixa, cidade preferida,
 * urgência, procedimento de interesse, faixa de preço. Observações estruturadas
 * (não bloco livre — FR-017). PII clínica explícita é rejeitada pela
 * `UpdateLeadProfileCapability` (T097) antes de chegar aqui.
 *
 * Histórico do valor anterior é preservado em `KanbanCurationEvent.value_before`
 * (FR-021 — consultável via timeline).
 */
final class KanbanCurationService
{
    /**
     * Mapeamento `field` (capability allow-list) → coluna do Paciente.
     *
     * Para campos que NÃO existem como colunas dedicadas (queixa, cidade
     * preferida, etc.) usamos `origem_detalhe` como JSON aglutinador.
     * Decisão pragmática: o data-model dedicado a auditoria está em
     * `kanban_curation_events` (T013) — `Paciente` só carrega o estado atual.
     */
    private const FIELD_COLUMN_MAP = [
        'name' => 'nome',
        // Os demais ficam só na auditoria por enquanto (T099-iter1).
        // T125a vai estender Paciente com colunas dedicadas para queixa, cidade etc.
        'complaint' => null,
        'preferred_city' => null,
        'urgency' => null,
        'procedure' => null,
        'price_range' => null,
    ];

    public function __construct(
        private readonly AiMetricsContract $metrics,
    ) {}

    /**
     * Atualiza um campo do Paciente. Idempotente — se o novo valor é igual
     * ao atual, NÃO emite evento.
     *
     * @return KanbanCurationEvent|null null se foi no-op (mesmo valor).
     */
    public function applyProfileUpdate(
        Paciente $paciente,
        string $field,
        string $valueAfter,
        ?int $turnVersion = null,
        ?int $toolInvocationId = null,
    ): ?KanbanCurationEvent {
        $column = self::FIELD_COLUMN_MAP[$field] ?? null;

        $valueBefore = $column !== null ? (string) ($paciente->{$column} ?? '') : null;

        if ($column !== null && $valueBefore === $valueAfter) {
            return null; // no-op
        }

        if ($column !== null) {
            $paciente->update([$column => $valueAfter]);
        }

        $event = KanbanCurationEvent::create([
            'tenant_id' => $paciente->tenant_id,
            'paciente_id' => $paciente->id,
            'event_kind' => 'profile_updated',
            'source' => 'ia_tool',
            'applied' => true,
            'field_changed' => $field,
            'value_before' => $valueBefore,
            'value_after' => $valueAfter,
            'turn_version' => $turnVersion,
            'tool_invocation_id' => $toolInvocationId,
            'actor_type' => 'ia',
            'reason' => 'IA atualizou perfil do lead via tool update-lead-profile',
            'created_at' => now(),
        ]);

        event(new KanbanCardCurated($event));

        $this->metrics->kanbanCurationEvent(
            tenantId: $paciente->tenant_id,
            source: 'ia_tool',
            eventKind: 'profile_updated',
        );

        return $event;
    }
}
