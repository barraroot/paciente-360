<?php

namespace App\Http\Controllers\Api\V1\Prescriptions;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Renewal\Exceptions\PrescriptionNotEligibleForRenewalException;
use App\Domain\Prescription\Renewal\InitiatedByType;
use App\Domain\Prescription\Renewal\RenewPrescriptionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Prescriptions\RenewPrescriptionRequest;
use App\Http\Resources\Prescriptions\PrescriptionRenewalResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * T135 — Controller de renovação de receitas (US-8.3).
 *
 * POST /api/v1/prescriptions/{prescription}/renew
 */
class PrescriptionRenewalController extends Controller
{
    public function __construct(
        private readonly RenewPrescriptionService $renewService,
    ) {}

    /**
     * Inicia uma renovação de receita.
     *
     * Gate de visualização obrigatório na prescription original (tenant-aware).
     * Retorna 201 + PrescriptionRenewalResource em sucesso.
     * Retorna 422 com `error=prescription_not_eligible_for_renewal` se inelegível.
     */
    public function store(RenewPrescriptionRequest $request, Prescription $prescription): JsonResponse
    {
        Gate::authorize('view', $prescription);

        // Defesa em profundidade: appointment_id deve pertencer ao tenant.
        $appointmentId = $request->integer('appointment_id', 0) ?: null;

        if ($appointmentId !== null) {
            $tenantId = app('tenant')?->id;
            $exists = DB::table('appointments')
                ->where('id', $appointmentId)
                ->where('tenant_id', $tenantId)
                ->exists();

            if (! $exists) {
                return response()->json([
                    'error' => 'invalid_appointment',
                    'message' => 'Appointment does not belong to the current tenant.',
                ], 422);
            }
        }

        try {
            $renewal = $this->renewService->initiate(
                original: $prescription,
                by: InitiatedByType::from($request->validated('initiated_by')),
                appointmentId: $appointmentId,
                userId: $request->user()?->id,
            );
        } catch (PrescriptionNotEligibleForRenewalException $e) {
            return response()->json([
                'error' => 'prescription_not_eligible_for_renewal',
                'reason' => $e->reason,
            ], 422);
        }

        return (new PrescriptionRenewalResource($renewal))
            ->response()
            ->setStatusCode(201);
    }
}
