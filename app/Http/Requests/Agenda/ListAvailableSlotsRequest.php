<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T080 — GET /agenda/slots-disponiveis (US-6.5 — IA Matricial usa).
 */
class ListAvailableSlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('appointment.view');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'professional_id' => ['required', 'integer', 'exists:professionals,id'],
            'appointment_type_id' => ['required', 'integer', 'exists:appointment_types,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
        ];
    }
}
