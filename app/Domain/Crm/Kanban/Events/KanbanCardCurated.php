<?php

declare(strict_types=1);

namespace App\Domain\Crm\Kanban\Events;

use App\Domain\Crm\Kanban\Models\KanbanCurationEvent;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * **T038 (Fase 18 — US3, FR-022)** — emitido sempre que o KanbanCurationService
 * ou o KanbanAutoTransitionService aplica/suprime uma mutação automática
 * no card.
 *
 * Inclui supressões (applied=false, suppression_reason) — FR-020 garante
 * que manual override do operador não é sobrescrito por automação.
 *
 * Persistido em `audit_logs` via PersistAuditLogListener.
 */
final class KanbanCardCurated implements Auditable
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly KanbanCurationEvent $curationEvent,
    ) {}

    public function auditAction(): string
    {
        return 'kanban.card.curated';
    }

    public function auditPayload(): array
    {
        return [
            'curation_event_id' => $this->curationEvent->id,
            'paciente_id' => $this->curationEvent->paciente_id,
            'event_kind' => $this->curationEvent->event_kind,
            'source' => $this->curationEvent->source,
            'applied' => $this->curationEvent->applied,
            'suppression_reason' => $this->curationEvent->suppression_reason,
            'from_coluna_id' => $this->curationEvent->from_coluna_id,
            'to_coluna_id' => $this->curationEvent->to_coluna_id,
            'field_changed' => $this->curationEvent->field_changed,
            'turn_version' => $this->curationEvent->turn_version,
        ];
    }

    public function auditableModel(): ?Model
    {
        return $this->curationEvent;
    }

    public function auditTenantId(): ?int
    {
        return $this->curationEvent->tenant_id;
    }

    public function auditUserId(): ?int
    {
        return $this->curationEvent->actor_type === 'user'
            ? $this->curationEvent->actor_id
            : null;
    }

    public function auditActorType(): ?string
    {
        return $this->curationEvent->actor_type;
    }
}
