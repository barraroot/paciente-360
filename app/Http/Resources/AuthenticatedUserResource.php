<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource do usuário autenticado — retornado no login e no endpoint /me.
 *
 * Nunca expõe: `password`, `remember_token`, `failed_login_attempts`, `locked_until`.
 *
 * Status mapeado:
 *  - `status='disabled'` → `'disabled'`
 *  - `status='invited'` → `'invited'`
 *  - `status='active'` → `'active'`
 *
 * Permissões: `getAllPermissions()` respeita o team mode do Spatie
 * (`permissions_team_id` setado pelo `TenantResolved` listener) — apenas
 * permissões do tenant ativo são retornadas.
 *
 * @see specs/001-fundacao-multitenant/contracts/openapi.yaml § AuthenticatedUserResource
 * @see App\Http\Controllers\Api\V1\Auth\LoginController
 * @see App\Http\Controllers\Api\V1\Auth\MeController
 */
final class AuthenticatedUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'tenant' => $this->whenLoaded(
                'tenant',
                fn () => TenantResource::make($this->tenant),
                fn () => $this->tenant_id !== null
                    ? TenantResource::make($this->tenant()->withoutGlobalScopes()->first())
                    : null
            ),
        ];
    }
}
