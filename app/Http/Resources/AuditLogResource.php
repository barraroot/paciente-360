<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para `AuditLog` (US-2.4 — FR-035, openapi.yaml § AuditLogResource).
 *
 * Shape:
 *  - `actor`: objeto com `type`, `user_id`, `user_name`, `email` — `user_name`
 *    e `email` populados via `whenLoaded('user')` para evitar N+1.
 *  - `auditable`: objeto `{type, id}` ou `null` quando o evento não referencia
 *    uma entidade específica.
 *  - `payload`: objeto livre — sanitizado upstream pelo `AuditAttributesBuilder`.
 *
 * @property AuditLog $resource
 *
 * @see specs/001-fundacao-multitenant/contracts/openapi.yaml § AuditLogResource
 */
final class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AuditLog $log */
        $log = $this->resource;

        return [
            'id' => $log->id,
            'actor' => [
                'type' => $log->actor_type,
                'user_id' => $log->user_id,
                'user_name' => $this->resolveUserField('name'),
                'email' => $this->resolveUserField('email'),
            ],
            'action' => $log->action,
            'auditable' => $log->auditable_type !== null && $log->auditable_id !== null
                ? [
                    'type' => $log->auditable_type,
                    'id' => $log->auditable_id,
                ]
                : null,
            'payload' => $log->payload ?? [],
            'ip' => $log->ip,
            'user_agent' => $log->user_agent,
            'request_id' => $log->request_id,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    /**
     * Extrai um campo do usuário ator com fallback para `null`.
     * Considera relação eager-loaded (`whenLoaded`) para evitar N+1.
     */
    private function resolveUserField(string $field): ?string
    {
        if (! $this->resource->relationLoaded('user')) {
            return null;
        }

        $user = $this->resource->user;

        return $user?->{$field};
    }
}
