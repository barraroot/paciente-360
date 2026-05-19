<?php

namespace App\Domain\Prescription\PrescriptionItem;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * T052 — Autocomplete de medicamentos via histórico do médico (Q2).
 *
 * Usa `pg_trgm % similarity` no índice GIN existente
 * (`idx_prescription_items_medication_name_trgm`) para sugestões
 * baseadas em prescrições dos últimos 12 meses do médico no tenant.
 */
class MedicationAutocompleteService
{
    /**
     * Sugere medicamentos com base no histórico do profissional.
     *
     * @return list<array{medication_name: string, count: int}>
     */
    public function suggestForProfessional(User $professional, string $query, int $limit = 10): array
    {
        if (trim($query) === '') {
            return [];
        }

        $since = now()->subMonths(12)->toDateString();

        $results = DB::select(<<<'SQL'
            SELECT
                pi.medication_name,
                COUNT(*) AS usage_count
            FROM prescription_items pi
            INNER JOIN prescriptions p ON p.id = pi.prescription_id
            WHERE p.professional_id = ?
              AND p.tenant_id = ?
              AND p.issued_at >= ?
              AND pi.medication_name % ?
            GROUP BY pi.medication_name
            ORDER BY similarity(pi.medication_name, ?) DESC, usage_count DESC
            LIMIT ?
        SQL, [
            $professional->id,
            $professional->tenant_id,
            $since,
            $query,
            $query,
            $limit,
        ]);

        return array_map(fn ($row) => [
            'medication_name' => $row->medication_name,
            'count' => (int) $row->usage_count,
        ], $results);
    }
}
