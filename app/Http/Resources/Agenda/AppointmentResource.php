<?php

namespace App\Http\Resources\Agenda;

use App\Models\Agenda\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T081 — AppointmentResource (US-6.3..6.7).
 *
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'paciente_id' => $this->paciente_id,
            'professional_id' => $this->professional_id,
            'appointment_type_id' => $this->appointment_type_id,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'status' => $this->status,
            'channel_origin' => $this->channel_origin,
            'valor_aplicado' => $this->valor_aplicado,
            'valor_override_motivo' => $this->valor_override_motivo,
            'override_block' => $this->override_block,
            'override_motivo' => $this->override_motivo,
            'motivo_cancelamento' => $this->motivo_cancelamento,
            'quem_cancelou' => $this->quem_cancelou,
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'attendance_marked_at' => $this->attendance_marked_at?->toIso8601String(),
            'attendance_marked_by_user_id' => $this->attendance_marked_by_user_id,
            'attendance_motivo' => $this->attendance_motivo,
            'auto_flagged_at' => $this->auto_flagged_at?->toIso8601String(),
            'notes' => $this->whenLoaded('paciente', $this->notes), // só expõe notes ao carregar paciente (UX)
            'reschedule_count' => $this->reschedules_count ?? $this->reschedules()->count(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
