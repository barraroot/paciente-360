<?php

namespace App\Http\Requests\Agenda;

use App\Models\Agenda\Appointment;
use Illuminate\Foundation\Http\FormRequest;

/**
 * T111 — POST /agenda/consultas/{id}/marcar-comparecimento (clarify nº 14).
 */
class MarkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $appointment = $this->route('appointment');

        return $appointment instanceof Appointment
            && $this->user()->can('appointment.update');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:realizada,nao_realizada'],
            'attendance_motivo' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
