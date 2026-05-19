<?php

namespace App\Http\Controllers\Api\V1\Prescriptions;

use App\Domain\Prescription\Alert\AlertStatus;
use App\Domain\Prescription\Alert\PrescriptionAlert;
use App\Domain\Prescription\Alert\PrescriptionAlertSchedulerService;
use App\Domain\Prescription\Prescription\Prescription;
use App\Http\Controllers\Controller;
use App\Http\Requests\Prescriptions\UpdateAlertConfigRequest;
use App\Http\Resources\Prescriptions\PrescriptionAlertResource;
use App\Http\Resources\Prescriptions\PrescriptionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * T110 — Controller de configuração de alertas de receita.
 *
 * GET  /api/v1/prescriptions/{prescription}/alerts       → index
 * PATCH /api/v1/prescriptions/{prescription}/alert-config → update
 */
class PrescriptionAlertConfigController extends Controller
{
    public function __construct(
        private readonly PrescriptionAlertSchedulerService $alertScheduler,
    ) {}

    /**
     * GET /api/v1/prescriptions/{prescription}/alerts
     *
     * Lista os alertas de uma receita.
     */
    public function index(Prescription $prescription): AnonymousResourceCollection
    {
        Gate::authorize('view', $prescription);

        return PrescriptionAlertResource::collection(
            $prescription->alerts()->orderBy('scheduled_for')->get()
        );
    }

    /**
     * PATCH /api/v1/prescriptions/{prescription}/alert-config
     *
     * Habilita/desabilita alertas de vencimento para uma receita common.
     *
     * disable (false → true): cancela alerts pending.
     * re-enable (true → false): reagenda checkpoints futuros.
     */
    public function update(
        UpdateAlertConfigRequest $request,
        Prescription $prescription,
    ): PrescriptionResource {
        $newValue = $request->boolean('alert_disabled');
        $wasDisabled = $prescription->alert_disabled;

        DB::transaction(function () use ($prescription, $newValue, $wasDisabled): void {
            $prescription->update(['alert_disabled' => $newValue]);

            if (! $wasDisabled && $newValue) {
                // false → true: cancela todos os alerts pending
                PrescriptionAlert::withoutTenantScope()
                    ->where('prescription_id', $prescription->id)
                    ->where('status', AlertStatus::Pending)
                    ->update(['status' => AlertStatus::Cancelled]);
            } elseif ($wasDisabled && ! $newValue) {
                // true → false: reagenda checkpoints futuros
                $this->alertScheduler->scheduleFor($prescription, onlyFuture: true);
            }
        });

        $prescription->refresh()->load('items');

        return new PrescriptionResource($prescription);
    }
}
