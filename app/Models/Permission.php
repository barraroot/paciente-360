<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Permission estendida (T061) — adiciona suporte a `tenant_id`.
 *
 * Schema canônico em `specs/001-fundacao-multitenant/data-model.md` § 5.2.
 *
 * Permissões com `tenant_id = NULL` são globais (template seedado em
 * `RolesSeeder`). Em fases futuras, cada tenant pode customizar suas
 * permissões via UI; o filtro por tenant é feito automaticamente pelo
 * Spatie quando `setPermissionsTeamId` é chamado.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property int|null $tenant_id
 */
class Permission extends SpatiePermission
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
