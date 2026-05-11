<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T153 — Resource para `EventoTimeline`.
 *
 * Serializa um evento da timeline com actor, referencia e payload.
 *
 * @see specs/002-crm-pacientes/contracts/openapi.yaml § EventoTimelineResource
 */
final class EventoTimelineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'actor' => [
                'type' => $this->actor_type,
                'user_id' => $this->autor_id,
                'user_name' => $this->when(
                    $this->relationLoaded('autor') && $this->autor !== null,
                    fn () => $this->autor->name,
                    null,
                ),
            ],
            'referencia' => $this->referencia_tipo !== null ? [
                'tipo' => $this->referencia_tipo,
                'id' => $this->referencia_id,
            ] : null,
            'payload' => $this->payload ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
