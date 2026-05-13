<?php

namespace App\Domain\Auth\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de deduplicação de emails cross-tenant (T031 — FR-001a / NC-1.a).
 *
 * Extração da lógica do `UsersDedupeEmailsCrossTenantCommand` para um service
 * reusável — permite chamada programática de detecção e resolução de duplicatas
 * sem depender do console.
 *
 * Responsabilidades:
 *  - `detectDuplicates()` — retorna emails duplicados entre tenants (excluindo soft-deleted).
 *  - `applyDedup(array $resolutions)` — aplica renomeações de email em transação, com audit log.
 *
 * O command `UsersDedupeEmailsCrossTenantCommand` delega para este service — sem lógica duplicada.
 *
 * @see App\Console\Commands\UsersDedupeEmailsCrossTenantCommand
 * @see specs/004-token-auth-migration/spec.md §FR-001a
 */
class EmailDedupService
{
    /**
     * Detecta emails que aparecem em mais de um tenant (excluindo soft-deleted).
     *
     * @return Collection<int, object{email: string, duplicate_count: int, tenants: list<string>, last_logins: list<string>}>
     */
    public function detectDuplicates(): Collection
    {
        $rawDuplicates = DB::table('users')
            ->select('email', DB::raw('COUNT(*) as duplicate_count'))
            ->whereNull('deleted_at')
            ->groupBy('email')
            ->having(DB::raw('COUNT(*)'), '>', 1)
            ->get();

        return $rawDuplicates->map(function (object $d): object {
            $users = User::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('email', $d->email)
                ->with('tenant')
                ->get();

            return (object) [
                'email' => $d->email,
                'duplicate_count' => (int) $d->duplicate_count,
                'tenants' => $users->map(fn (User $u): string => $u->tenant?->slug ?? "tenant_{$u->tenant_id}")->values()->toArray(),
                'last_logins' => $users->map(fn (User $u): string => ($u->tenant?->slug ?? "t{$u->tenant_id}").': '.($u->last_login_at?->format('Y-m-d') ?? 'never'))->values()->toArray(),
            ];
        });
    }

    /**
     * Aplica as resoluções de deduplicação em transação.
     *
     * Cada resolution deve ter:
     *  - `email` (string) — email original duplicado
     *  - `keep_tenant_id` (int) — tenant que mantém o email original
     *  - `suffix_tenants` (array<int>) — IDs dos tenants que recebem sufixo `.tenant-{slug}`
     *
     * Audit log gerado por cada renomeação (Log::info).
     *
     * @param array<int, array{email: string, keep_tenant_id: int, suffix_tenants: list<int>}> $resolutions
     */
    public function applyDedup(array $resolutions): void
    {
        DB::transaction(function () use ($resolutions): void {
            foreach ($resolutions as $resolution) {
                $email = $resolution['email'];
                $keepTenantId = $resolution['keep_tenant_id'];
                $suffixTenantIds = $resolution['suffix_tenants'] ?? [];

                foreach ($suffixTenantIds as $tenantId) {
                    if ((int) $tenantId === (int) $keepTenantId) {
                        continue;
                    }

                    $user = User::withoutGlobalScopes()
                        ->whereNull('deleted_at')
                        ->where('email', $email)
                        ->where('tenant_id', $tenantId)
                        ->first();

                    if ($user === null) {
                        continue;
                    }

                    $slug = $user->tenant?->slug ?? "t{$tenantId}";
                    $newEmail = "{$email}.tenant-{$slug}";

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['email' => $newEmail, 'updated_at' => now()]);

                    Log::info('user.email_renamed_for_global_uniqueness', [
                        'user_id' => $user->id,
                        'old_email' => $email,
                        'new_email' => $newEmail,
                        'tenant_id' => $tenantId,
                        'keep_tenant_id' => $keepTenantId,
                    ]);
                }
            }
        });
    }
}
