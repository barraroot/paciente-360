<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Domain\Reports\Services\OperationalReportService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Reports\OperationalReportResource;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * **T269 (Fase 8 — Lote E US-10.2)** — API do Relatório Operacional.
 */
class OperationalReportController extends Controller
{
    public function __construct(
        private readonly OperationalReportService $service,
    ) {}

    public function show(Request $request): OperationalReportResource
    {
        Gate::authorize('report.view');

        $end = $request->filled('end') ? Carbon::parse($request->string('end')) : Carbon::now();
        $start = $request->filled('start') ? Carbon::parse($request->string('start')) : $end->copy()->subDays(30);

        $data = $this->service->build(app('tenant'), $start, $end);

        return new OperationalReportResource($data);
    }
}
