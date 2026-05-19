<?php

namespace App\Domain\Prescription\Alert;

use App\Domain\Prescription\Prescription\Prescription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * T096 — Serviço de agendamento de alertas de vencimento.
 *
 * Materializa os 3 checkpoints D-15/D-7/D-1 para uma receita no momento
 * da criação (ou re-enable de alertas).
 *
 * Regras:
 *  - `alert_disabled = true` → nenhum alerta criado (válido apenas para `common`).
 *  - `scheduled_for < today` → status=`skipped`, `skip_reason='checkpoint_past_at_creation'`.
 *  - Caso contrário → status=`pending`.
 *  - Denormaliza `tenant_id` em cada alerta.
 */
final class PrescriptionAlertSchedulerService
{
    /**
     * Cria (ou recria) os alertas de vencimento para uma receita.
     *
     * Retorna a collection dos alertas criados.
     * Deve ser chamado dentro da mesma transação DB do create/re-enable.
     *
     * @param bool $onlyFuture Se true, só cria alertas com scheduled_for >= today (para re-enable).
     * @return Collection<int, PrescriptionAlert>
     */
    public function scheduleFor(Prescription $prescription, bool $onlyFuture = false): Collection
    {
        // Alertas desabilitados → nenhum registro
        if ($prescription->alert_disabled) {
            return collect();
        }

        $today = CarbonImmutable::today();
        $expiresAt = CarbonImmutable::instance($prescription->expires_at);
        $created = collect();

        foreach (AlertType::cases() as $alertType) {
            $scheduledFor = $expiresAt->subDays($alertType->daysBefore());

            // Re-enable: pula checkpoints passados (não cria rows para datas passadas)
            if ($onlyFuture && $scheduledFor->lessThan($today)) {
                continue;
            }

            $isPast = $scheduledFor->lessThan($today);
            $status = $isPast ? AlertStatus::Skipped : AlertStatus::Pending;
            $skipReason = $isPast ? 'checkpoint_past_at_creation' : null;

            $alert = PrescriptionAlert::create([
                'prescription_id' => $prescription->id,
                'tenant_id' => $prescription->tenant_id,
                'alert_type' => $alertType,
                'status' => $status,
                'scheduled_for' => $scheduledFor->toDateString(),
                'skip_reason' => $skipReason,
            ]);

            $created->push($alert);
        }

        return $created;
    }
}
