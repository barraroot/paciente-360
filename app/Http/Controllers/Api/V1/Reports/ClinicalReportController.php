<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Domain\Reports\Services\ClinicalReportService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Reports\ClinicalReportResource;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * **T275 (Fase 8 — Lote E US-10.3)** — API do Relatório Clínico.
 *
 * Escopo por perfil (Q13) aplicado pelo Service via `professionalScopeFor($user)`.
 */
class ClinicalReportController extends Controller
{
    public function __construct(
        private readonly ClinicalReportService $service,
    ) {}

    public function show(Request $request): ClinicalReportResource
    {
        Gate::authorize('report.view');

        $end = $request->filled('end') ? Carbon::parse($request->string('end')) : Carbon::now();
        $start = $request->filled('start') ? Carbon::parse($request->string('start')) : $end->copy()->subDays(30);

        $data = $this->service->build(app('tenant'), $start, $end, $request->user());

        return new ClinicalReportResource($data);
    }
}
