<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T134a — POST /agenda/waitlist (US-6.6).
 */
class StoreWaitlistEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('waitlist.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'paciente_id' => ['required', 'integer', 'exists:pacientes,id'],
            'professional_id' => ['required', 'integer', 'exists:professionals,id'],
            'appointment_type_id' => ['required', 'integer', 'exists:appointment_types,id'],
        ];
    }
}
