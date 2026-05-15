<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T079 — POST /agenda/slots/{starts_at}/reservar (clarify nº 2).
 */
class ReserveSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('appointment.create');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'appointment_type_id' => ['required', 'integer', 'exists:appointment_types,id'],
            'professional_id' => ['required', 'integer', 'exists:professionals,id'],
            'holder_type' => ['required', 'in:user,ia'],
            'holder_id' => ['required', 'string', 'max:64'],
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }
}
