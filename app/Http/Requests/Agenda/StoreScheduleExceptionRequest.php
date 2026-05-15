<?php

namespace App\Http\Requests\Agenda;

use App\Models\Professional;
use App\Policies\Agenda\ProfessionalSchedulePolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * T040 — Form request para POST /agenda/professionals/{p}/schedule-exceptions.
 */
class StoreScheduleExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $professional = $this->route('professional');

        if (! $professional instanceof Professional) {
            return false;
        }

        return (new ProfessionalSchedulePolicy)->update($this->user(), $professional);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
