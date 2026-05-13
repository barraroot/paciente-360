<?php

namespace App\Http\Requests\Agenda;

use App\Models\Agenda\AppointmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * T053a — Form request para criar tipo de atendimento (US-6.2).
 */
class StoreAppointmentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AppointmentType::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'min:1', 'max:100'],
            'duration_minutes' => ['required', 'integer', 'between:5,600'],
            'buffer_minutes' => ['nullable', 'integer', 'between:0,120'],
            'valor_particular' => ['required', 'numeric', 'min:0'],
            'valor_convenio_default' => ['nullable', 'numeric', 'min:0'],
            'min_cancellation_hours' => ['nullable', 'integer', 'min:0'],
            'cor' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'descricao' => ['nullable', 'string'],
            'intent_ia' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function passedValidation(): void
    {
        $tenantId = $this->user()->tenant_id;
        $base = Str::slug($this->validated()['nome']);
        $slug = $base;
        $suffix = 0;

        while (AppointmentType::query()->where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        $this->merge(['slug' => $slug]);
    }
}
