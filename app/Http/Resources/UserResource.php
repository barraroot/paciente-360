<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource público de `User` — subset usado em respostas onde NÃO se deve
 * vazar permissions ou tenant aninhado (ex.: response do cadastro público,
 * listagem de usuários do tenant atual).
 *
 * Difere de `AuthenticatedUserResource` por NÃO incluir:
 *  - `permissions` (interno; só faz sentido para o próprio usuário logado).
 *  - `tenant` (escopado; em endpoints públicos tem cardinalidade clara).
 *
 * @see specs/001-fundacao-multitenant/contracts/openapi.yaml § UserResource
 * @see App\Http\Resources\AuthenticatedUserResource
 */
final class UserResource extends JsonResource
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
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
