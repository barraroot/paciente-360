<?php

namespace App\Domain\Prescription\Report;

use App\Domain\Prescription\Prescription\Prescription;
use App\Models\User;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Carbon;

/**
 * T156 — Serviço de relatório de receitas com cursor pagination.
 *
 * Índices utilizados pelo query plan:
 *  - `idx_prescriptions_tenant_status_expires` (tenant_id, status, expires_at)
 *    → Cobre o filtro por status + janela de vencimento (cláusula default).
 *  - `idx_prescriptions_tenant_type_expires` (tenant_id, type, expires_at)
 *    → Cobre o filtro por type + janela de vencimento.
 *  - `idx_prescriptions_tenant_patient` (tenant_id, patient_id)
 *    → Cobre o filtro por patient_id.
 *  - `idx_prescriptions_tenant_professional` (tenant_id, professional_id)
 *    → Cobre o filtro por professional_id.
 *  - Cursor pagination usa o índice primário (id) como fallback de cursor.
 *
 * @see specs/007-gestao-receituario/data-model.md §2
 * @see https://laravel.com/docs/13.x/pagination#cursor-pagination
 *
 * DEFERRED — Sentry transaction tracing: este serviço é candidato a
 * instrumentação via Sentry::startTransaction() quando o SDK estiver
 * configurado em produção. Medir `prescription_report_query_ms` por tenant.
 */
class PrescriptionReportService
{
    /**
     * Pagina receitas do tenant usando cursor pagination.
     *
     * Filtros combinados em AND (AC-8.4.3):
     *  - status: PrescriptionStatus value
     *  - type: PrescriptionType value
     *  - professional_id: int
     *  - patient_id: int
     *  - expires_after: date string (inclusive)
     *  - expires_before: date string (inclusive)
     *
     * Window default (AC-8.4.8): quando expires_after/expires_before não
     * fornecidos, restringe a expires_at entre hoje e hoje + 30 dias.
     *
     * Ordenação default: expires_at ASC (T168 — cursor pagination requer ORDER BY).
     *
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters, User $viewer, int $perPage = 50): CursorPaginator
    {
        $query = Prescription::query()->with(['items', 'patient', 'professional']);

        // Filtros AND combinados
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

        // Janela de vencimento: default hoje → hoje+30d se não especificada (AC-8.4.8)
        $hasExpiresAfter = ! empty($filters['expires_after']);
        $hasExpiresBefore = ! empty($filters['expires_before']);

        if ($hasExpiresAfter || $hasExpiresBefore) {
            if ($hasExpiresAfter) {
                $query->whereDate('expires_at', '>=', $filters['expires_after']);
            }

            if ($hasExpiresBefore) {
                $query->whereDate('expires_at', '<=', $filters['expires_before']);
            }
        } else {
            // Window default: hoje até hoje+30d
            $today = Carbon::today();
            $query->whereDate('expires_at', '>=', $today->toDateString())
                ->whereDate('expires_at', '<=', $today->copy()->addDays(30)->toDateString());
        }

        // Cursor pagination exige ORDER BY na(s) coluna(s) do cursor (T168)
        $query->orderBy('expires_at')->orderBy('id');

        return $query->cursorPaginate($perPage);
    }
}
