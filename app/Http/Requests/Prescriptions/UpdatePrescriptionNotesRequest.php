<?php

namespace App\Http\Requests\Prescriptions;

use App\Domain\Prescription\Prescription\Prescription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * T061 — Validação de atualização de notas de receita (AC-8.1.7).
 *
 * APENAS `notes` é editável após save. Tipo, validade, medicamentos e
 * posologia são imutáveis — corrija via cancelamento + nova receita.
 */
class UpdatePrescriptionNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Prescription $prescription */
        $prescription = $this->route('prescription');

        return Gate::allows('update', $prescription);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
