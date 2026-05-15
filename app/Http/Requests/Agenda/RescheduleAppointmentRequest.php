<?php

namespace App\Http\Requests\Agenda;

use App\Models\Agenda\Appointment;
use Illuminate\Foundation\Http\FormRequest;

/**
 * T078 — POST /agenda/consultas/{id}/reagendar (clarify nº 7).
 *
 * Mantém prof+tipo (não aceitos no payload). Limite 2 reagendamentos
 * (FR-026b — enforce no service).
 */
class RescheduleAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $appointment = $this->route('appointment');

        return $appointment instanceof Appointment
            && $this->user()->can('reschedule', $appointment);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'uuid'],
            'new_starts_at' => ['required', 'date'],
            'motivo' => ['nullable', 'string'],
            'quem_solicitou' => ['nullable', 'in:paciente,atendente,profissional,ia'],
        ];
    }
}
