<?php

namespace App\Http\Requests\Prescriptions;

use App\Domain\Prescription\Prescription\Prescription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * T062 — Validação de cancelamento de receita (AC-8.1.8).
 *
 * Cancelamento exige categoria ∈ {erro_emissao, desistencia_paciente,
 * substituicao, outro} e texto livre (≤ 500 chars).
 */
class CancelPrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Prescription $prescription */
        $prescription = $this->route('prescription');

        return Gate::allows('cancel', $prescription);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cancellation_reason_category' => [
                'required',
                Rule::in(['erro_emissao', 'desistencia_paciente', 'substituicao', 'outro']),
            ],
            'cancellation_reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cancellation_reason_category.required' => 'A categoria de cancelamento é obrigatória.',
            'cancellation_reason_category.in' => 'A categoria deve ser: erro_emissao, desistencia_paciente, substituicao ou outro.',
            'cancellation_reason.required' => 'O motivo do cancelamento é obrigatório.',
            'cancellation_reason.max' => 'O motivo não pode exceder 500 caracteres.',
        ];
    }
}
