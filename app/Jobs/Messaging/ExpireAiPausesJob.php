<?php

namespace App\Jobs\Messaging;

use App\Domain\Messaging\Conversation\Contracts\ConversaIATogglingContract;
use App\Domain\Messaging\Conversation\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\Permission\PermissionRegistrar;

/**
 * T174 — Job cross-tenant que expira pausas de IA vencidas.
 *
 * Roda a cada minuto (schedule). Encontra TODAS as conversas com
 * `ai_paused_until < now()` (qualquer tenant) e chama `resumeAi(..., 'timeout')`.
 *
 * Cross-tenant por design: sem `TenantAwareJob` porque precisa processar
 * múltiplos tenants em uma única execução. Usa `withoutGlobalScopes()`
 * para bypassar o tenant scope global, e seta o Spatie team_id por conversa
 * para que o audit log seja corretamente atribuído ao tenant.
 *
 * Idempotente: chunkById garante que conversas já zeradas (ai_paused_until=null)
 * não apareçam na query numa segunda execução.
 */
final class ExpireAiPausesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function handle(ConversaIATogglingContract $contract): void
    {
        Conversation::withoutGlobalScopes()
            ->whereNotNull('ai_paused_until')
            ->where('ai_paused_until', '<', now())
            ->chunkById(100, function ($conversations) use ($contract): void {
                foreach ($conversations as $conversation) {
                    app(PermissionRegistrar::class)->setPermissionsTeamId($conversation->tenant_id);

                    $contract->resumeAi($conversation, 'timeout');
                }
            });
    }
}
