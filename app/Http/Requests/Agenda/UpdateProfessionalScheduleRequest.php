<?php

namespace App\Http\Requests\Agenda;

use App\Models\Agenda\ProfessionalSchedule;
use App\Models\Professional;
use App\Policies\Agenda\ProfessionalSchedulePolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * T039 — Form request para PUT /agenda/professionals/{p}/schedules.
 */
class UpdateProfessionalScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $professional = $this->route('professional');

        if (! $professional instanceof Professional) {
            return false;
        }

        return $this->user()->can('update', [ProfessionalSchedule::class, $professional])
            || (new ProfessionalSchedulePolicy)->update($this->user(), $professional);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'timezone' => ['nullable', 'string', 'max:64'],
            'schedules' => ['required', 'array', 'min:1'],
            'schedules.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'schedules.*.blocks' => ['required', 'array', 'min:1'],
            'schedules.*.blocks.*.start' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'schedules.*.blocks.*.end' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'schedules.*.effective_from' => ['nullable', 'date_format:Y-m-d'],
            'schedules.*.effective_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:schedules.*.effective_from'],
            'effective_from' => ['nullable', 'date_format:Y-m-d'],
            'accepted_appointment_type_ids' => ['nullable', 'array'],
            'accepted_appointment_type_ids.*' => ['integer', 'exists:appointment_types,id'],
        ];
    }
}
