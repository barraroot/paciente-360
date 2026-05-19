<?php

namespace App\Domain\Prescription\Report;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionType;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Metrics\PrescriptionMetrics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * T157 — Exportador CSV de receitas com mascaramento e audit.
 *
 * Regras:
 *  - Itera via lazyById (chunk-based) para evitar OOM em datasets grandes.
 *  - Chama ControlledPrescriptionMaskingService em cada linha.
 *  - Filename: CONFIDENCIAL_receituarios_{slug}_{date}.csv se ≥1 controlada.
 *  - Audit log: action='prescription.csv_exported' com IDs exportados.
 *
 * @see specs/007-gestao-receituario/spec.md Q6d
 */
class PrescriptionCsvExporter
{
    public function __construct(
        private readonly ControlledPrescriptionMaskingService $maskingService,
        private readonly PrescriptionMetrics $metrics,
    ) {}

    /**
     * Exporta receitas filtradas como CSV streamed.
     *
     * @param array<string, mixed> $filters
     */
    public function export(array $filters, User $exporter): StreamedResponse
    {
        // Primeiro passo: materializar IDs e verificar se há controladas
        // (necessário para decidir filename antes de stremar).
        $prescriptions = $this->buildQuery($filters)->get(['id', 'type', 'tenant_id']);

        $allIds = $prescriptions->pluck('id')->all();
        $hasControlled = $prescriptions->contains(
            fn (Prescription $p) => $p->type === PrescriptionType::Controlled
        );

        $tenantSlug = $exporter->tenant?->slug ?? (string) $exporter->tenant_id;
        $date = Carbon::today()->toDateString();

        $filename = $hasControlled
            ? "CONFIDENCIAL_receituarios_{$tenantSlug}_{$date}.csv"
            : "receituarios_{$tenantSlug}_{$date}.csv";

        // Audit log antes de começar o stream (AC-8.4.7)
        $this->recordAuditLog($exporter, $allIds, $hasControlled);

        // Métrica
        $this->metrics->csvExported($exporter->tenant_id, count($allIds), $hasControlled);

        return response()->streamDownload(function () use ($filters, $exporter): void {
            $out = fopen('php://output', 'w');

            // BOM UTF-8 para compatibilidade Excel
            fwrite($out, "\xEF\xBB\xBF");

            // Header
            fputcsv($out, [
                'id',
                'patient_id',
                'professional_name',
                'type',
                'status',
                'issued_at',
                'expires_at',
                'criticality',
                'notes',
            ]);

            // Lazy iterator por ID para não carregar tudo em memória
            $this->buildQuery($filters)
                ->with(['patient', 'professional', 'items'])
                ->lazyById()
                ->each(function (Prescription $prescription) use ($out, $exporter): void {
                    $masked = $this->maskingService->mask($prescription, $exporter);
                    $isMasked = $masked['masked'] ?? false;

                    fputcsv($out, [
                        $prescription->id,
                        $prescription->patient_id,
                        $isMasked ? '[RESTRITO]' : ($prescription->professional?->name ?? ''),
                        $prescription->type?->value ?? '',
                        $prescription->status?->value ?? '',
                        $prescription->issued_at?->toDateString() ?? '',
                        $prescription->expires_at?->toDateString() ?? '',
                        $prescription->criticality(),
                        $isMasked ? '[RESTRITO]' : ($prescription->notes ?? ''),
                    ]);
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Constrói query com filtros sem paginação.
     *
     * @param array<string, mixed> $filters
     * @return Builder<Prescription>
     */
    private function buildQuery(array $filters): Builder
    {
        $query = Prescription::query();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['professional_id'])) {
            $query->where('professional_id', (int) $filters['professional_id']);
        }

        if (! empty($filters['patient_id'])) {
            $query->where('patient_id', (int) $filters['patient_id']);
        }

        if (! empty($filters['expires_after'])) {
            $query->whereDate('expires_at', '>=', $filters['expires_after']);
        }

        if (! empty($filters['expires_before'])) {
            $query->whereDate('expires_at', '<=', $filters['expires_before']);
        }

        return $query->orderBy('expires_at')->orderBy('id');
    }

    /**
     * Registra audit log da exportação.
     *
     * @param array<int> $ids
     */
    private function recordAuditLog(User $exporter, array $ids, bool $hasControlled): void
    {
        try {
            AuditLog::create([
                'tenant_id' => $exporter->tenant_id,
                'user_id' => $exporter->id,
                'actor_type' => 'user',
                'action' => 'prescription.csv_exported',
                'auditable_type' => Prescription::class,
                'auditable_id' => null,
                'payload' => [
                    'ids' => $ids,
                    'count' => count($ids),
                    'contained_controlled' => $hasControlled,
                ],
                'ip' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('prescription.csv_export.audit_failed', ['error' => $e->getMessage()]);
        }
    }
}
