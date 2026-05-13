<?php

namespace App\Http\Resources\Agenda;

use App\Models\Agenda\AppointmentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T054 — AppointmentTypeResource (US-6.2).
 *
 * @mixin AppointmentType
 */
class AppointmentTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'slug' => $this->slug,
            'duration_minutes' => $this->duration_minutes,
            'buffer_minutes' => $this->buffer_minutes,
            'valor_particular' => $this->valor_particular,
            'valor_convenio_default' => $this->valor_convenio_default,
            'min_cancellation_hours' => $this->min_cancellation_hours,
            'cor' => $this->cor,
            'descricao' => $this->descricao,
            'intent_ia' => $this->intent_ia,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
