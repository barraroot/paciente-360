<?php

namespace App\Http\Requests\Agenda;

use App\Models\Agenda\AppointmentType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * T053b — Form request para PATCH /agenda/appointment-types/{id} (US-6.2).
 */
class UpdateAppointmentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $type = $this->route('appointment_type');

        return $type instanceof AppointmentType
            && $this->user()->can('update', $type);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'required', 'string', 'min:1', 'max:100'],
            'duration_minutes' => ['sometimes', 'required', 'integer', 'between:5,600'],
            'buffer_minutes' => ['sometimes', 'integer', 'between:0,120'],
            'valor_particular' => ['sometimes', 'required', 'numeric', 'min:0'],
            'valor_convenio_default' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'min_cancellation_hours' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'cor' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'descricao' => ['sometimes', 'nullable', 'string'],
            'intent_ia' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
