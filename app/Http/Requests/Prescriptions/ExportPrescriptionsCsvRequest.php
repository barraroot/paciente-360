<?php

namespace App\Http\Requests\Prescriptions;

use App\Domain\Prescription\Prescription\PrescriptionStatus;
use App\Domain\Prescription\Prescription\PrescriptionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * T160 — Validação de filtros para exportação CSV de receitas.
 *
 * Autorização via ability `prescription.export`.
 * Filtros idênticos ao ListPrescriptionsRequest (AC-8.4.5).
 */
class ExportPrescriptionsCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('prescription.export') ?? false;
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
        ];
    }
}
