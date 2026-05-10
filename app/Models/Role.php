<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Role estendido (T061) — adiciona suporte a `tenant_id` (team_foreign_key
 * do Spatie em modo teams).
 *
 * Schema canônico em `specs/001-fundacao-multitenant/data-model.md` § 5.1.
 *
 * IMPORTANTE: este Model **não usa** `BelongsToTenant`. O escopo por
 * tenant é gerenciado pelo próprio Spatie via `setPermissionsTeamId` no
 * listener de `TenantResolved` (ver `AppServiceProvider::boot`). Roles
 * com `tenant_id = NULL` são **templates globais** (clonados ao criar
 * tenant) e **roles globais reais** (ex.: `super-admin`).
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property int|null $tenant_id
 */
class Role extends SpatieRole
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'guard_name',
        'tenant_id',
    ];
}
