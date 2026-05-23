<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use App\Models\Agenda\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **T027 (Fase 10 — Spec 010 / US-2)** — Resource de item de próxima consulta.
 *
 * @see specs/010-dashboard-home/data-model.md § 1.3
 *
 * @mixin Appointment
 */
final class UpcomingAppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $patientName = (string) ($this->paciente?->nome ?? '—');

        return [
            'id' => $this->id,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'starts_at_local' => $this->starts_at?->format('H:i'),
            'patient_name' => mb_strlen($patientName) > 60 ? mb_substr($patientName, 0, 57).'…' : $patientName,
            'appointment_type' => $this->appointmentType?->nome ?? 'Consulta',
            'professional_name' => $this->professional?->name ?? '—',
            'status' => $this->status,
        ];
    }
}
