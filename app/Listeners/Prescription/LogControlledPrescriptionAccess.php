<?php

namespace App\Listeners\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Events\Prescription\PrescricaoControladaVisualizada;
use App\Models\AuditLog;

/**
 * T059 — Grava em audit_logs cada acesso autorizado ao conteúdo de receita
 * controlada (Q8c — log de evento, não snapshot de campos).
 *
 * Auto-discovered pelo Laravel 13 via type-hint do `handle()`.
 * NÃO registrar manualmente em AppServiceProvider.
 */
class LogControlledPrescriptionAccess
{
    public function handle(PrescricaoControladaVisualizada $event): void
    {
        try {
            AuditLog::create([
                'tenant_id' => $event->tenantId,
                'user_id' => $event->actorUserId,
                'actor_type' => 'user',
                'action' => 'prescription.view_controlled',
                'auditable_type' => Prescription::class,
                'auditable_id' => $event->prescriptionId,
                'payload' => [
                    'prescription_id' => $event->prescriptionId,
                    'viewed_at' => $event->viewedAt->toIso8601String(),
                ],
                'ip' => $event->ip,
                'user_agent' => $event->userAgent,
            ]);
        } catch (\Throwable) {
            // Audit failures must never block the response (defense in depth)
        }
    }
}
