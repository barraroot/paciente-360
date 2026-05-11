<?php

namespace App\Listeners\Paciente;

use App\Events\Contracts\Auditable;
use App\Models\EventoTimeline;
use App\Models\Paciente;
use App\Services\Audit\AuditAttributesBuilder;
use Illuminate\Support\Facades\Log;

/**
 * T035 — Listener que projeta eventos `Auditable` de domínio
 * (Paciente/Anotação/Tag) em `eventos_timeline`.
 *
 * Coexiste com o `PersistAuditLogListener` (Fase 0) — ambos escutam
 * `Auditable::class` e são chamados em sequência pelo dispatcher.
 *
 * Regra de gravação:
 *  - Pega o `paciente_id` via `IsAuditable::relatedPacienteId()` (hook
 *    com default heurístico). Eventos sem paciente único (ex.: importação
 *    em lote) retornam `null` e são ignorados aqui (não poluem timeline).
 *  - `payload` reaproveita o mesmo `auditPayload()` saneado via
 *    `AuditAttributesBuilder::sanitizePayload` (CPF mascarado etc).
 *  - Falhas são logadas mas NÃO interrompem o request (mesmo princípio
 *    do `PersistAuditLogListener` — observabilidade não-obstrutiva).
 *
 * Registrado em `EventServiceProvider::boot()` via
 * `Event::listen(Auditable::class, RegistraEventoTimelineListener::class)`.
 *
 * @see App\Listeners\PersistAuditLogListener
 * @see specs/002-crm-pacientes/data-model.md §7
 */
class RegistraEventoTimelineListener
{
    public function __construct(
        private readonly AuditAttributesBuilder $builder,
    ) {}

    public function __invoke(Auditable $event): void
    {
        try {
            $pacienteId = method_exists($event, 'relatedPacienteId')
                ? $event->relatedPacienteId()
                : null;

            if ($pacienteId === null) {
                // Evento não está atrelado a um paciente único —
                // operações em lote (importação/exportação) ficam
                // exclusivamente no audit_logs.
                return;
            }

            $tenantId = $event->auditTenantId();

            if ($tenantId === null) {
                // Sem tenant resolvido, não há linha de timeline a inserir.
                // Eventos do Super Admin (cross-tenant) também caem aqui;
                // o audit log preserva o rastro.
                return;
            }

            $userId = $event->auditUserId();
            $actorType = $userId !== null ? 'user' : 'system';

            $auditableOverride = $event->auditActorType();
            if ($auditableOverride !== null && in_array($auditableOverride, ['user', 'system', 'webhook'], true)) {
                $actorType = $auditableOverride;
            }

            $auditable = $event->auditableModel();
            $referenciaTipo = null;
            $referenciaId = null;
            if ($auditable !== null && ! $auditable instanceof Paciente) {
                $referenciaTipo = $auditable::class;
                $referenciaId = $auditable->getKey();
            }

            // Reusa o sanitizer do AuditAttributesBuilder via build() para
            // pegar o payload sanitizado (CPF mascarado etc).
            $payload = $this->builder->build($event)['payload'];

            EventoTimeline::create([
                'tenant_id' => $tenantId,
                'paciente_id' => $pacienteId,
                'tipo' => $event->auditAction(),
                'autor_id' => $userId,
                'actor_type' => $actorType,
                'payload' => $payload,
                'referencia_tipo' => $referenciaTipo,
                'referencia_id' => $referenciaId,
            ]);
        } catch (\Throwable $e) {
            Log::error('RegistraEventoTimelineListener: failed to persist timeline event', [
                'exception' => $e->getMessage(),
                'action' => $event->auditAction(),
            ]);
        }
    }
}
