<?php

namespace App\Services\Users;

use App\Events\Users\UserDisabled;
use App\Events\Users\UserPermissionsChanged;
use App\Exceptions\Users\LastAdminClinicaException;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;

/**
 * Serviço de gestão de usuários internos (US-2.2, FR-026–029).
 *
 * Regras de negócio:
 *  - Last Admin Guard: nunca reduz para 0 admins-clinica ativos no tenant.
 *  - Role allowlist: mesma de InvitationService (não permite `super-admin`).
 *  - Desativação: soft-delete via `status='disabled'`; preserva auditoria.
 */
final class UserService
{
    /**
     * Lista usuários do tenant com filtros opcionais.
     *
     * @param array{status?: string, role?: string, page?: int, per_page?: int} $filters
     * @return LengthAwarePaginator<User>
     */
    public function list(Tenant $tenant, array $filters = []): LengthAwarePaginator
    {
        $query = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['role'])) {
            $query->whereHas('roles', function ($q) use ($filters): void {
                $q->where('name', $filters['role'])
                    ->where('team_foreign_key_value', $filters['tenant_id'] ?? null);
            });
        }

        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $query->paginate($perPage);
    }

    /**
     * Atualiza nome e/ou roles do usuário.
     *
     * @param array{name?: string, roles?: list<string>} $data
     *
     * @throws LastAdminClinicaException quando a mudança de role deixaria o tenant sem admin.
     * @throws \InvalidArgumentException quando uma role não está na allowlist.
     */
    public function update(User $user, array $data): User
    {
        if (isset($data['name'])) {
            $user->name = $data['name'];
        }

        if (isset($data['roles'])) {
            $this->assertRolesAllowlist($data['roles']);
            $this->assertLastAdminGuard($user, $data['roles']);

            $actor = Auth::user();
            $oldRoles = $user->getRoleNames()->values()->all();

            app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
            $user->syncRoles($data['roles']);

            $newRoles = $user->fresh()->getRoleNames()->values()->all();

            if ($oldRoles !== $newRoles && $actor instanceof User) {
                Event::dispatch(new UserPermissionsChanged($user, $actor, $oldRoles, $newRoles));
            }
        }

        $user->save();

        return $user->fresh();
    }

    /**
     * Desativa o usuário (status → 'disabled'). Preserva o registro para auditoria.
     *
     * @throws LastAdminClinicaException quando o usuário é o último admin-clinica ativo.
     */
    public function disable(User $user): void
    {
        $this->assertLastAdminGuard($user, []);

        $actor = Auth::user();

        $user->status = 'disabled';
        $user->save();

        if ($actor instanceof User) {
            Event::dispatch(new UserDisabled($user, $actor));
        }
    }

    /**
     * Verifica que as roles informadas estão na allowlist (sem `super-admin`).
     *
     * @param list<string> $roles
     *
     * @throws \InvalidArgumentException
     */
    private function assertRolesAllowlist(array $roles): void
    {
        foreach ($roles as $role) {
            if (! in_array($role, InvitationService::ALLOWED_ROLES, true)) {
                throw new \InvalidArgumentException("Role '{$role}' não é permitida.");
            }
        }
    }

    /**
     * Verifica que a operação não deixa o tenant sem nenhum `admin-clinica` ativo.
     *
     * Cenários verificados:
     *  - Disable: se o usuário tem role `admin-clinica` e é o único admin ativo.
     *  - Update roles: se a nova lista de roles não inclui `admin-clinica` e o
     *    usuário é o único admin-clinica ativo.
     *
     * @param list<string> $newRoles lista de roles após a operação (vazio = disable).
     *
     * @throws LastAdminClinicaException
     */
    private function assertLastAdminGuard(User $user, array $newRoles): void
    {
        $tenantId = $user->tenant_id;

        if ($tenantId === null) {
            return;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

        if (! $user->hasRole('admin-clinica')) {
            return;
        }

        // Se a operação mantém admin-clinica na nova lista de roles, tudo ok.
        if (in_array('admin-clinica', $newRoles, true)) {
            return;
        }

        // Conta quantos admins-clinica ativos existem no tenant (excluindo o próprio).
        $adminCount = User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin-clinica'))
            ->count();

        if ($adminCount <= 1) {
            throw new LastAdminClinicaException;
        }
    }
}
