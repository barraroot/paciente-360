<?php

namespace App\Domain\Messaging\Presence\Services;

use App\Domain\Messaging\Presence\Models\UserPresence;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * T262 — Serviço de rastreamento de presença de atendentes (NC-6.b).
 *
 * Status inferido por `last_seen_at` (último heartbeat via HTTP POST /presence/heartbeat).
 * Limiar padrão: 5 minutos sem heartbeat = offline.
 * Configurável via `config('messaging.auto_assign.user_idle_minutes')`.
 */
class PresenceTrackerService
{
    /**
     * Registra heartbeat do usuário — atualiza `last_seen_at` para agora.
     *
     * Cria a linha na tabela se não existir (upsert por tenant_id + user_id).
     */
    public function heartbeat(User $user): UserPresence
    {
        return UserPresence::withoutGlobalScopes()
            ->updateOrCreate(
                [
                    'tenant_id' => $user->tenant_id,
                    'user_id' => $user->id,
                ],
                [
                    'last_seen_at' => now(),
                    'status' => 'online',
                ],
            );
    }

    /**
     * Lista todos os atendentes do tenant com status inferido.
     *
     * Retorna apenas usuários que tenham `inbox.view` ability — filtro
     * aplicado ao carregar usuários relacionados.
     *
     * @return Collection<int, array{user_id: int, name: string, email: string, status: string, last_seen_at: string|null}>
     */
    public function listForTenant(int $tenantId): Collection
    {
        return UserPresence::withoutGlobalScopes()
            ->with('user')
            ->where('tenant_id', $tenantId)
            ->get()
            ->filter(fn (UserPresence $presence) => $presence->user !== null
                && $presence->user->can('inbox.view'))
            ->map(fn (UserPresence $presence) => [
                'user_id' => $presence->user_id,
                'name' => $presence->user->name,
                'email' => $presence->user->email,
                'status' => $this->inferStatus($presence->last_seen_at),
                'last_seen_at' => $presence->last_seen_at?->toIso8601String(),
                'max_concurrent_conversations' => $presence->max_concurrent_conversations,
                'current_assigned_count' => $presence->current_assigned_count,
            ])
            ->values();
    }

    /**
     * Infere status do atendente baseado no timestamp do último heartbeat.
     *
     * @param Carbon|null $lastSeenAt Timestamp do último heartbeat
     * @param int $idleMinutes Minutos de inatividade para considerar offline (padrão NC-6.b: 5min)
     */
    public function inferStatus(?Carbon $lastSeenAt, int $idleMinutes = 0): string
    {
        if ($idleMinutes === 0) {
            $idleMinutes = (int) config('messaging.auto_assign.user_idle_minutes', 5);
        }

        if ($lastSeenAt === null) {
            return 'offline';
        }

        return $lastSeenAt->greaterThan(now()->subMinutes($idleMinutes))
            ? 'online'
            : 'offline';
    }
}
