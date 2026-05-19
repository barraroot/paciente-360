<?php

namespace App\Jobs\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionStatus;
use App\Events\Prescription\ReceitaVencida;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * T104 — Job de expiração de receitas ativas vencidas.
 *
 * Scan: `Prescription::active` com `expires_at < today`.
 * Para cada uma:
 *  - Atomic-update `status='superseded'` only if still `active`
 *    (update() retorna 0 se já foi alterada → idempotente).
 *  - Emite `ReceitaVencida` (sem dados clínicos).
 *
 * Idempotência garantida pelo update condicional: se o job rodar 2x,
 * a segunda passagem não encontra mais receitas com status='active'.
 */
class ExpireActivePrescriptionsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function handle(): void
    {
        $today = CarbonImmutable::today();

        Prescription::withoutGlobalScopes()
            ->where('status', PrescriptionStatus::Active)
            ->whereDate('expires_at', '<', $today->toDateString())
            ->each(function (Prescription $prescription) use ($today): void {
                // Atomic-update condicional — idempotente
                $updated = Prescription::withoutGlobalScopes()
                    ->where('id', $prescription->id)
                    ->where('status', PrescriptionStatus::Active)
                    ->update(['status' => PrescriptionStatus::Superseded]);

                if ($updated === 1) {
                    ReceitaVencida::dispatch(
                        prescriptionId: $prescription->id,
                        patientId: $prescription->patient_id,
                        expiredAt: $today,
                    );
                }
            });
    }

    /** @return array<string> */
    public function tags(): array
    {
        return ['prescription-expire-active'];
    }
}
