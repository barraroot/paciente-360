<?php

namespace App\Http\Controllers\Api\V1\Prescriptions;

use App\Domain\Prescription\Prescription\CancellationReasonCategory;
use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Prescriptions\CancelPrescriptionRequest;
use App\Http\Requests\Prescriptions\ListPrescriptionsRequest;
use App\Http\Requests\Prescriptions\StorePrescriptionRequest;
use App\Http\Requests\Prescriptions\UpdatePrescriptionNotesRequest;
use App\Http\Resources\Prescriptions\PrescriptionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * T068 — Controller de receitas (CRUD + cancel).
 *
 * Autorização via Policy. Validação via FormRequests.
 * Lógica de domínio no PrescriptionService.
 */
class PrescriptionController extends Controller
{
    public function __construct(
        private readonly PrescriptionService $prescriptionService,
    ) {}

    /**
     * GET /api/v1/prescriptions
     */
    public function index(ListPrescriptionsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Prescription::class);

        $query = Prescription::query()->with(['items', 'patient', 'professional']);

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->integer('patient_id'));
        }

        if ($request->filled('professional_id')) {
            $query->where('professional_id', $request->integer('professional_id'));
        }

        if ($request->filled('expires_after')) {
            $query->whereDate('expires_at', '>=', $request->input('expires_after'));
        }

        if ($request->filled('expires_before')) {
            $query->whereDate('expires_at', '<=', $request->input('expires_before'));
        }

        $prescriptions = $query
            ->orderBy('expires_at')
            ->paginate($request->integer('per_page', 20));

        return PrescriptionResource::collection($prescriptions);
    }

    /**
     * POST /api/v1/prescriptions
     */
    public function store(StorePrescriptionRequest $request): JsonResponse
    {
        $prescription = $this->prescriptionService->create(
            data: $request->validated(),
            author: $request->user(),
        );

        $prescription->load('items');

        return (new PrescriptionResource($prescription))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/prescriptions/{prescription}
     */
    public function show(Prescription $prescription): PrescriptionResource
    {
        Gate::authorize('view', $prescription);

        $prescription->load('items');

        return new PrescriptionResource($prescription);
    }

    /**
     * PATCH /api/v1/prescriptions/{prescription}
     */
    public function update(UpdatePrescriptionNotesRequest $request, Prescription $prescription): PrescriptionResource
    {
        $this->prescriptionService->updateNotes(
            prescription: $prescription,
            notes: $request->validated()['notes'] ?? '',
            actor: $request->user(),
        );

        $prescription->load('items');

        return new PrescriptionResource($prescription);
    }

    /**
     * POST /api/v1/prescriptions/{prescription}/cancel
     */
    public function cancel(CancelPrescriptionRequest $request, Prescription $prescription): PrescriptionResource
    {
        $this->prescriptionService->cancel(
            prescription: $prescription,
            category: CancellationReasonCategory::from($request->validated()['cancellation_reason_category']),
            reason: $request->validated()['cancellation_reason'],
            by: $request->user(),
        );

        $prescription->refresh()->load('items');

        return new PrescriptionResource($prescription);
    }
}
