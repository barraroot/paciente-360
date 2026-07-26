<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Domain\Reports\Events\RelatorioExportado;
use App\Domain\Reports\Models\ReportExport;
use App\Domain\Reports\Services\DashboardPdfRenderer;
use App\Domain\Reports\Services\ExecutiveDashboardService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Reports\ExecutiveDashboardResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

/**
 * **T255 (Fase 8 — Lote E US-10.1)** — API do Dashboard Executivo.
 *
 * Endpoints:
 *   - GET  /api/v1/reports/executive                    → snapshot
 *   - GET  /api/v1/reports/executive/drill/{metric}    → drill-down lista
 *   - POST /api/v1/reports/executive/export-pdf        → PDF formatado
 */
class ExecutiveDashboardController extends Controller
{
    public function __construct(
        private readonly ExecutiveDashboardService $service,
        private readonly DashboardPdfRenderer $pdfRenderer,
    ) {}

    public function show(Request $request): ExecutiveDashboardResource
    {
        Gate::authorize('report.view');

        [$start, $end, $previousStart, $previousEnd] = $this->resolvePeriod($request);
        $tenant = app('tenant');

        $data = $this->service->getKpis($tenant, $start, $end, $request->user(), $previousStart, $previousEnd);

        return new ExecutiveDashboardResource($data);
    }

    public function drillDown(Request $request, string $metric): ExecutiveDashboardResource
    {
        Gate::authorize('report.view');

        [$start, $end] = $this->resolvePeriod($request);

        $data = $this->service->drillDown(app('tenant'), $metric, $start, $end);

        return new ExecutiveDashboardResource($data);
    }

    public function exportPdf(Request $request): Response
    {
        Gate::authorize('report.export');

        [$start, $end, $previousStart, $previousEnd] = $this->resolvePeriod($request);
        $tenant = app('tenant');
        $user = $request->user();

        $data = $this->service->getKpis($tenant, $start, $end, $user, $previousStart, $previousEnd);
        $pdfBytes = $this->pdfRenderer->renderPdf($tenant, $data);

        // Audit + persistência em report_exports.
        $now = Carbon::now();
        ReportExport::query()->create([
            'tenant_id' => $tenant->id,
            'tipo' => 'executive_dashboard',
            'formato' => 'pdf',
            'filters_applied' => ['period_start' => $start->toIso8601String(), 'period_end' => $end->toIso8601String()],
            'exported_by_user_id' => $user->id,
            'exported_at' => $now,
            'file_size_bytes' => strlen($pdfBytes),
        ]);

        Event::dispatch(new RelatorioExportado(
            tenantId: $tenant->id,
            tipo: 'executive_dashboard',
            formato: 'pdf',
            filtersApplied: ['period_start' => $start->toIso8601String(), 'period_end' => $end->toIso8601String()],
            exportedByUserId: $user->id,
            exportedAt: $now,
        ));

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="dashboard-'.$start->format('Y-m-d').'.pdf"',
        ]);
    }

    /**
     * Resolve a janela [start, end] e, para o preset "Hoje" (`1d`), também o
     * período anterior explícito (ontem 00:00 → hoje 00:00) usado pelos deltas.
     *
     * @return array{0: Carbon, 1: Carbon, 2: ?Carbon, 3: ?Carbon}
     */
    private function resolvePeriod(Request $request): array
    {
        $preset = $request->string('preset')->toString();
        $endRaw = $request->string('end')->toString();
        $startRaw = $request->string('start')->toString();

        $end = $endRaw !== '' ? Carbon::parse($endRaw) : Carbon::now();
        $start = $startRaw !== '' ? Carbon::parse($startRaw) : match ($preset) {
            '1d' => $end->copy()->startOfDay(),
            '24h' => $end->copy()->subHours(24),
            '7d' => $end->copy()->subDays(7),
            '90d' => $end->copy()->subDays(90),
            default => $end->copy()->subDays(30),
        };

        // Limite máximo de 12 meses (NFR).
        $maxMonths = (int) config('finalization.report_max_window_months', 12);
        $maxStart = $end->copy()->subMonths($maxMonths);
        if ($start->lessThan($maxStart)) {
            $start = $maxStart;
        }

        // Preset "Hoje" (1d): período anterior é o dia anterior completo
        // (ontem 00:00 → hoje 00:00) — não as últimas 24h a partir de agora.
        // Demais presets mantêm a derivação padrão do Service (null aqui).
        $previousStart = null;
        $previousEnd = null;
        if ($preset === '1d' && $startRaw === '') {
            $previousEnd = $end->copy()->startOfDay();
            $previousStart = $previousEnd->copy()->subDay();
        }

        return [$start, $end, $previousStart, $previousEnd];
    }
}
