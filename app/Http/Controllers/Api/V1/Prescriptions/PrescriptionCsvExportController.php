<?php

namespace App\Http\Controllers\Api\V1\Prescriptions;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Report\PrescriptionCsvExporter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Prescriptions\ExportPrescriptionsCsvRequest;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * T159 — Controller para exportação CSV de receitas.
 *
 * GET /api/v1/prescription-reports/export — CSV streamed.
 *
 * Autorização: ability `prescription.export` via Policy::export.
 * Delegação de domínio: PrescriptionCsvExporter::export().
 */
class PrescriptionCsvExportController extends Controller
{
    public function __construct(
        private readonly PrescriptionCsvExporter $csvExporter,
    ) {}

    /**
     * GET /api/v1/prescription-reports/export
     *
     * Exporta receitas filtradas como CSV (AC-8.4.5/7).
     */
    public function export(ExportPrescriptionsCsvRequest $request): StreamedResponse
    {
        Gate::authorize('export', Prescription::class);

        return $this->csvExporter->export(
            filters: $request->validated(),
            exporter: $request->user(),
        );
    }
}
