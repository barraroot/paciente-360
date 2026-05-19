<?php

namespace App\Http\Controllers\Api\V1\Prescriptions;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Report\PrescriptionReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Prescriptions\ListPrescriptionsRequest;
use App\Http\Resources\Prescriptions\PrescriptionReportRowResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * T158 — Controller do relatório de receitas.
 *
 * GET /api/v1/prescription-reports — cursor paginated report.
 *
 * Autorização: ability `prescription.view` via Policy::viewAny.
 * Delegação de domínio: PrescriptionReportService::paginate().
 */
class PrescriptionReportController extends Controller
{
    public function __construct(
        private readonly PrescriptionReportService $reportService,
    ) {}

    /**
     * GET /api/v1/prescription-reports
     *
     * Retorna relatório paginado via cursor (AC-8.4.1).
     */
    public function index(ListPrescriptionsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Prescription::class);

        $paginator = $this->reportService->paginate(
            filters: $request->validated(),
            viewer: $request->user(),
            perPage: $request->integer('per_page', 50),
        );

        return PrescriptionReportRowResource::collection($paginator);
    }
}
