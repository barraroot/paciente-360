<?php

namespace App\Http\Requests\Agenda;

use App\Models\Agenda\Appointment;
use Illuminate\Foundation\Http\FormRequest;

/**
 * T122 — POST /agenda/consultas/{id}/cancelar (US-6.5 / clarify nº 3).
 */
class CancelAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $appointment = $this->route('appointment');

        return $appointment instanceof Appointment
            && $this->user()->can('cancel', $appointment);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'uuid'],
            'motivo' => ['required', 'string', 'min:1', 'max:1000'],
            'quem_cancelou' => ['required', 'in:paciente,atendente,profissional'],
        ];
    }
}
