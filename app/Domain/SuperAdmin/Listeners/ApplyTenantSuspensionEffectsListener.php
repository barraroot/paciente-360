<?php

declare(strict_types=1);

namespace App\Domain\SuperAdmin\Listeners;

use App\Domain\SuperAdmin\Events\TenantSuspenso;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * **T093 (Fase 8 — Lote B US-12.1)** — Aplica efeitos colaterais de suspensão.
 *
 * Quando Super Admin suspende um tenant:
 *   1. Revoga TODOS os Sanctum tokens dos usuários do tenant (FR-004a pattern Fase 4).
 *   2. Loga marker para jobs em fila do tenant pausarem na próxima execução.
 *   3. Marca cache de session do Filament (admin panel) para logout no próximo
 *      request (DEFERRED — depende de mecanismo de session storage do Filament).
 *
 * Auto-discovered. Fila default — execução assíncrona evita bloquear o
 * endpoint de suspensão.
 */
final class ApplyTenantSuspensionEffectsListener implements ShouldQueue
{
    public function handle(TenantSuspenso $event): void
    {
        $tenantId = $event->tenantId;

        // 1. Revoga personal_access_tokens dos usuários do tenant.
        $userIds = User::query()
            ->where('tenant_id', $tenantId)
            ->pluck('id');

        $revokedCount = 0;
        if ($userIds->isNotEmpty()) {
            $revokedCount = PersonalAccessToken::query()
                ->whereIn('tokenable_id', $userIds)
                ->where('tokenable_type', User::class)
                ->delete();
        }

        // 2. Marker em log estruturado para o Horizon supervisor identificar
        //    jobs do tenant suspenso na próxima leitura. Implementação real
        //    do pausing fica DEFERRED — supervisor manual ou Lote B futuro.
        Log::warning('super_admin.tenant.suspension_effects_applied', [
            'tenant_id' => $tenantId,
            'suspended_by_user_id' => $event->suspendedByUserId,
            'tokens_revoked' => $revokedCount,
            'users_affected' => $userIds->count(),
            'reason' => $event->reason,
        ]);
    }
}
