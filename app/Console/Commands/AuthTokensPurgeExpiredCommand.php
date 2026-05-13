<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * T090 — Purga `personal_access_tokens` expirados/revogados há > 90 dias.
 *
 * Política de retenção (FR-006 / NC-2):
 *  - Tokens com `expires_at < now() - 90d` → DELETE
 *  - Tokens revogados (`expires_at` no passado por revogação manual) seguem
 *    o mesmo critério: 90d após a revogação, deletados.
 *  - Tokens sem `expires_at` (legado pré-Fase 4 — improvável) são ignorados.
 *
 * Por que 90 dias? FR-006 estabelece janela de auditoria de 90d para tokens
 * revogados — permite investigação retroativa de comportamento suspeito
 * (TokenUsoSuspeito) sem manter rastros indefinidamente (Princípio I — LGPD
 * Art. 16 — limitação de retenção).
 *
 * Cross-tenant: opera globalmente (sem TenantAwareJob) — tokens são
 * `tokenable_type=User` e o filter é por `expires_at`, não por tenant.
 *
 * Cobertura por tenant é refletida no log estruturado para auditoria.
 *
 * @see config/sanctum.php (sliding expiration)
 * @see app/Domain/Auth/Services/SlidingExpirationService.php (renovação)
 * @see specs/004-token-auth-migration/spec.md §FR-006 retention
 */
final class AuthTokensPurgeExpiredCommand extends Command
{
    protected $signature = 'auth:tokens-purge-expired
        {--dry-run : Lista tokens elegíveis sem deletar}
        {--keep-days=90 : Janela de retenção em dias (default 90)}';

    protected $description = 'Purga personal_access_tokens expirados/revogados há > N dias (retention housekeeping).';

    public function handle(): int
    {
        $keepDays = (int) $this->option('keep-days');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($keepDays);

        $eligible = DB::table('personal_access_tokens')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $cutoff);

        $total = (int) $eligible->count();

        if ($total === 0) {
            $this->info("Nenhum token elegível (expires_at < {$cutoff->toIso8601String()}).");

            return self::SUCCESS;
        }

        // Conta por tenant (via join lazy — só para o log estruturado).
        $perTenant = DB::table('personal_access_tokens')
            ->join('users', 'users.id', '=', 'personal_access_tokens.tokenable_id')
            ->whereNotNull('personal_access_tokens.expires_at')
            ->where('personal_access_tokens.expires_at', '<', $cutoff)
            ->where('personal_access_tokens.tokenable_type', User::class)
            ->groupBy('users.tenant_id')
            ->selectRaw('users.tenant_id, COUNT(*) as total')
            ->pluck('total', 'tenant_id')
            ->all();

        if ($dryRun) {
            $this->info("DRY-RUN: {$total} token(s) elegível(is) para purga (cutoff {$cutoff->toIso8601String()}).");
            foreach ($perTenant as $tenantId => $count) {
                $this->line("  • tenant_id={$tenantId}: {$count} token(s)");
            }

            return self::SUCCESS;
        }

        $deleted = $eligible->delete();

        Log::info('auth.tokens.purged', [
            'cutoff' => $cutoff->toIso8601String(),
            'total_deleted' => $deleted,
            'per_tenant' => $perTenant,
            'keep_days' => $keepDays,
        ]);

        $this->info("Purgados {$deleted} token(s) (cutoff {$cutoff->toIso8601String()}).");

        return self::SUCCESS;
    }
}
