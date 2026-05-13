<?php

namespace App\Http\Requests\Agenda;

use App\Models\Agenda\Appointment;
use Illuminate\Foundation\Http\FormRequest;

/**
 * T076 — POST /agenda/consultas (US-6.3 + IA Matricial via channel_origin=ia).
 */
class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()->can('create', Appointment::class)) {
            return false;
        }

        // FR-002a — override_block exige ability dedicada
        if ($this->boolean('override_block') && ! $this->user()->can('appointment.override_block')) {
            return false;
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'uuid'],
            'paciente_id' => ['required', 'integer', 'exists:pacientes,id'],
            'professional_id' => ['required', 'integer', 'exists:professionals,id'],
            'appointment_type_id' => ['required', 'integer', 'exists:appointment_types,id'],
            'starts_at' => ['required', 'date'],
            'channel_origin' => ['required', 'in:painel,ia,autoatendimento'],
            'valor_override' => ['nullable', 'numeric', 'min:0'],
            'valor_override_motivo' => ['required_with:valor_override', 'nullable', 'string'],
            'override_block' => ['boolean'],
            'override_motivo' => ['required_if:override_block,true', 'nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'notify_patient' => ['nullable', 'boolean'],
            'reservation_id' => ['nullable', 'integer', 'exists:slot_reservations,id'],
        ];
    }
}
