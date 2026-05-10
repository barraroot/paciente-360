<?php

namespace App\Jobs\Users;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job de limpeza de convites expirados há mais de 30 dias (T245).
 *
 * Cross-tenant: NÃO usa `TenantAwareJob` — opera sobre todas as linhas
 * da tabela `invitations` sem scope. Roda como Super Admin de sistema.
 *
 * Idempotente: deletar linhas já deletadas não causa erro; re-execução
 * é segura.
 *
 * Agendado semanalmente em `routes/console.php` aos domingos às 03:00.
 */
final class PurgeExpiredInvitationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function handle(): void
    {
        Invitation::query()
            ->withoutGlobalScopes()
            ->where('expires_at', '<', now()->subDays(30))
            ->whereNull('accepted_at')
            ->delete();
    }
}
