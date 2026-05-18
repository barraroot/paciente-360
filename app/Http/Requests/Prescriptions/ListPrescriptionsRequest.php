<?php

namespace App\Http\Requests\Prescriptions;

use App\Domain\Prescription\Prescription\PrescriptionStatus;
use App\Domain\Prescription\Prescription\PrescriptionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * T063 — Validação de filtros na listagem de receitas.
 */
class ListPrescriptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization done in controller via Gate::authorize('viewAny')
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(PrescriptionStatus::class)],
            'type' => ['nullable', Rule::enum(PrescriptionType::class)],
            'patient_id' => ['nullable', 'integer'],
            'professional_id' => ['nullable', 'integer'],
            'expires_after' => ['nullable', 'date'],
            'expires_before' => ['nullable', 'date', 'after_or_equal:expires_after'],
            'cursor' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
