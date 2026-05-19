<?php

namespace App\Jobs\Prescription;

use App\Domain\Prescription\Alert\PrescriptionAlert;
use App\Domain\Prescription\Prescription\Prescription;
use App\Events\Prescription\ReceitaProximaDoVencimento;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * T103 — Job para despachar um único alerta de vencimento de receita.
 *
 * Carrega a Prescription, calcula daysUntilExpiry e emite
 * ReceitaProximaDoVencimento. O listener DispatchPrescriptionAlertViaMessaging
 * faz o envio efetivo via mensageria.
 *
 * @see App\Listeners\Prescription\DispatchPrescriptionAlertViaMessaging
 */
class DispatchPrescriptionAlertJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        private readonly int $alertId,
    ) {
        $this->onQueue('prescription-alerts');
    }

    public function handle(): void
    {
        $alert = PrescriptionAlert::withoutTenantScope()->find($this->alertId);

        if ($alert === null) {
            return;
        }

        $prescription = Prescription::withoutGlobalScopes()
            ->with('professional')
            ->find($alert->prescription_id);

        if ($prescription === null) {
            return;
        }

        $today = CarbonImmutable::today();
        $expiresAt = CarbonImmutable::instance($prescription->expires_at);
        $daysUntilExpiry = $today->diffInDays($expiresAt, absolute: false);

        if ($daysUntilExpiry < 0) {
            $daysUntilExpiry = 0;
        }

        $professionalName = $prescription->professional?->name ?? '';

        try {
            if (function_exists('\Sentry\configureScope')) {
                \Sentry\configureScope(function ($scope) use ($alert, $prescription): void {
                    $scope->setTag('prescription.id', (string) $prescription->id);
                    $scope->setTag('prescription.type', $prescription->type->value);
                    $scope->setTag('alert.type', $alert->alert_type->value);
                });
            }
        } catch (\Throwable) {
            // Sentry ausente em testes — não bloquear
        }

        ReceitaProximaDoVencimento::dispatch(
            prescriptionId: $prescription->id,
            patientId: $prescription->patient_id,
            professionalId: $prescription->professional_id,
            professionalName: $professionalName,
            daysUntilExpiry: (int) $daysUntilExpiry,
            prescriptionType: $prescription->type,
            defaultAppointmentTypeId: null,
        );
    }

    /** @return array<string> */
    public function tags(): array
    {
        return [
            'prescription-alert',
            "alert:{$this->alertId}",
        ];
    }
}
