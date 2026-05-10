<?php

namespace App\Http\Controllers\Api\V1\Audit;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Services\Audit\AuditService;
use App\Support\Csv\CsvExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller do Painel de Auditoria (US-2.4 — FR-035/036/037).
 *
 * Pipeline: Request → Controller → AuditService (query scoped) → Resource.
 *
 *  - `GET /audit-logs`         lista paginada (max per_page=200).
 *  - `GET /audit-logs/export`  CSV streaming com escape contra formula injection.
 *
 * Autorização: `AuditLogPolicy::viewAny` (admin-clinica, financeiro,
 * super-admin via Gate::before).
 *
 * Isolamento de tenant: garantido pelo `AuditService::query()` que filtra
 * por `app('tenant')`. Super Admin (sem tenant resolvido) tem painel
 * próprio em Filament (fora deste escopo).
 *
 * @group Audit
 *
 * @see App\Services\Audit\AuditService
 * @see App\Support\Csv\CsvExporter
 */
final class AuditLogsController extends Controller
{
    /**
     * Cap máximo de itens por página (espelha `openapi.yaml`).
     */
    private const PER_PAGE_MAX = 200;

    private const PER_PAGE_DEFAULT = 50;

    /**
     * Lista paginada do log de auditoria.
     *
     * Suporta filtros por `from`, `to`, `action` e `actor_user_id`.
     * Máximo de 200 itens por página (cap silencioso).
     *
     * @response 200 scenario="logs" {"data": [{"id": 1, "action": "auth.login", "created_at": "2026-05-10T10:00:00Z"}]}
     */
    public function index(Request $request, AuditService $service): ResourceCollection
    {
        Gate::authorize('viewAny', AuditLog::class);

        $query = $this->applyFilters($service->query(), $request);

        $perPage = $this->resolvePerPage($request);

        $paginator = $query
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return AuditLogResource::collection($paginator);
    }

    /**
     * Exportar logs como CSV.
     *
     * Mesmos filtros do `index`, sem paginação. UTF-8 com BOM para
     * compatibilidade com Excel. Seguro contra formula injection.
     *
     * @response 200 scenario="csv" {"Content-Type": "text/csv"}
     */
    public function export(Request $request, AuditService $service, CsvExporter $exporter): StreamedResponse
    {
        Gate::authorize('viewAny', AuditLog::class);

        $query = $this->applyFilters($service->query(), $request)
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $filename = 'audit-logs-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(
            static fn () => $exporter->streamAuditLogs($query),
            $filename,
            [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ],
        );
    }

    /**
     * Aplica filtros opcionais ao builder (from, to, action, actor_user_id).
     *
     * @param Builder<AuditLog> $query
     * @return Builder<AuditLog>
     */
    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', Carbon::parse((string) $from));
        }

        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', Carbon::parse((string) $to));
        }

        if ($action = $request->query('action')) {
            $query->where('action', (string) $action);
        }

        if ($actorUserId = $request->query('actor_user_id')) {
            $query->where('user_id', (int) $actorUserId);
        }

        return $query;
    }

    /**
     * Resolve `per_page` com cap silencioso em `PER_PAGE_MAX` (200).
     *
     * Cap silencioso (em vez de 422) reduz fricção do consumer e mantém
     * paridade com `simplePaginate` do Laravel.
     */
    private function resolvePerPage(Request $request): int
    {
        $value = (int) $request->query('per_page', self::PER_PAGE_DEFAULT);

        if ($value <= 0) {
            return self::PER_PAGE_DEFAULT;
        }

        return min($value, self::PER_PAGE_MAX);
    }
}
