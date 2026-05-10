<?php

namespace App\Jobs\Audit;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job de deleção física de audit logs expirados (T264 — US-2.4, FR-038).
 *
 * Remove de `audit_logs_cold` os registros com `created_at <
 * now() - delete_after_days` (default 1825 = 5 anos).
 *
 * Cumpre LGPD Art. 16 (minimização de dados após cumprimento da
 * finalidade); a auditoria já cobriu o período retroativo máximo
 * exigido por compliance (Princípio I).
 *
 * Imutabilidade & DBA bypass:
 *  - `audit_logs_cold` tem trigger `audit_logs_cold_immutable_trg`
 *    que precisa ser desabilitada para o DELETE em massa, igual à
 *    estratégia do `ArchiveAuditLogsJob`. Em produção exige role com
 *    privilégio `OWNER` sobre a tabela cold.
 *
 * @see App\Jobs\Audit\ArchiveAuditLogsJob
 * @see specs/001-fundacao-multitenant/data-model.md § 9
 */
final class DeleteExpiredAuditLogsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function handle(): void
    {
        $batchSize = (int) config('audit.archive_batch_size', 1000);
        $deleteAfterDays = (int) config('audit.delete_after_days', 1825);
        $cutoff = now()->subDays($deleteAfterDays);

        $totalDeleted = 0;

        while (true) {
            $deleted = $this->deleteBatch($cutoff, $batchSize);

            if ($deleted === 0) {
                break;
            }

            $totalDeleted += $deleted;
        }

        Log::info('audit.delete.completed', [
            'cutoff' => $cutoff->toIso8601String(),
            'total_deleted' => $totalDeleted,
        ]);
    }

    /**
     * Apaga um batch de registros expirados de `audit_logs_cold`.
     * Retorna o número de linhas afetadas.
     */
    private function deleteBatch(\DateTimeInterface $cutoff, int $batchSize): int
    {
        return (int) DB::transaction(function () use ($cutoff, $batchSize): int {
            $ids = DB::table('audit_logs_cold')
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                return 0;
            }

            DB::statement('ALTER TABLE audit_logs_cold DISABLE TRIGGER audit_logs_cold_immutable_trg');

            try {
                $affected = DB::table('audit_logs_cold')
                    ->whereIn('id', $ids->all())
                    ->delete();
            } finally {
                DB::statement('ALTER TABLE audit_logs_cold ENABLE TRIGGER audit_logs_cold_immutable_trg');
            }

            return (int) $affected;
        });
    }
}
