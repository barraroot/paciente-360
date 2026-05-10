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
 * Job de arquivamento de audit logs (T264 — US-2.4, FR-038).
 *
 * Move registros de `audit_logs` (hot, 0–2 anos) para `audit_logs_cold`
 * (cold, 2–5 anos) quando `created_at < now() - hot_days` (default 730).
 *
 * Operação em batches transacionais:
 *  - Cada batch: SELECT → INSERT em cold → DELETE em hot, em transação.
 *  - Tamanho controlado por `audit.archive_batch_size` (default 1000).
 *  - Idempotente: rodar 2x não duplica — uma vez DELETADO da hot, o
 *    registro não retorna; o cold filter `created_at < cutoff` faz o
 *    SELECT vir vazio.
 *
 * Imutabilidade & DBA bypass (data-model.md § 9):
 *  - Tabela `audit_logs` tem trigger PG `audit_logs_immutable_trg` que
 *    rejeita UPDATE/DELETE para qualquer client. Para que este job
 *    consiga mover registros, **a trigger é desabilitada temporariamente
 *    durante a transação**.
 *  - **Em produção**: este job deve rodar com uma role do PostgreSQL
 *    que tenha privilégio `TRIGGER` específico, e o `ALTER TABLE
 *    ... DISABLE TRIGGER` exige `OWNER` (ou `SUPERUSER`). O ideal é
 *    criar uma role `audit_archiver` dona da tabela e configurar a queue
 *    `audit-retention` com credenciais dessa role.
 *  - **Em testes/dev**: o usuário Sail Postgres é dono da tabela, então
 *    funciona out-of-the-box.
 *
 * Cross-tenant: NÃO usa `TenantAwareJob` — opera sobre TODAS as linhas
 * hot que cruzaram o limiar de tempo, independente de tenant.
 *
 * @see App\Jobs\Audit\DeleteExpiredAuditLogsJob
 * @see specs/001-fundacao-multitenant/data-model.md § 9
 */
final class ArchiveAuditLogsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800; // 30min — tabelas grandes.

    public function handle(): void
    {
        $batchSize = (int) config('audit.archive_batch_size', 1000);
        $hotDays = (int) config('audit.hot_days', 730);
        $cutoff = now()->subDays($hotDays);

        $totalMoved = 0;

        while (true) {
            $moved = $this->archiveBatch($cutoff, $batchSize);

            if ($moved === 0) {
                break;
            }

            $totalMoved += $moved;
        }

        Log::info('audit.archive.completed', [
            'cutoff' => $cutoff->toIso8601String(),
            'total_moved' => $totalMoved,
        ]);
    }

    /**
     * Move um batch (até `$batchSize`) de registros para o cold tier.
     * Retorna a quantidade efetivamente movida. Operação transacional —
     * INSERT + DELETE são all-or-nothing para evitar perda em crash.
     */
    private function archiveBatch(\DateTimeInterface $cutoff, int $batchSize): int
    {
        return (int) DB::transaction(function () use ($cutoff, $batchSize): int {
            $rows = DB::table('audit_logs')
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            if ($rows->isEmpty()) {
                return 0;
            }

            $ids = $rows->pluck('id')->all();

            $coldRows = $rows->map(function (object $row): array {
                $data = (array) $row;

                // Reinjeta o id original para preservar correlação cross-tier.
                return $data;
            })->all();

            DB::table('audit_logs_cold')->insertOrIgnore($coldRows);

            // Desabilita trigger PG de imutabilidade temporariamente.
            // Exige OWNER da tabela (ver PHPDoc do Job).
            DB::statement('ALTER TABLE audit_logs DISABLE TRIGGER audit_logs_immutable_trg');

            try {
                DB::table('audit_logs')->whereIn('id', $ids)->delete();
            } finally {
                DB::statement('ALTER TABLE audit_logs ENABLE TRIGGER audit_logs_immutable_trg');
            }

            return count($ids);
        });
    }
}
