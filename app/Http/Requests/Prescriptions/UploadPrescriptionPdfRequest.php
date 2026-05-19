<?php

namespace App\Http\Requests\Prescriptions;

use App\Domain\Prescription\Prescription\Prescription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * T064 — Validação de upload de PDF (AC-8.1.4 / Q7).
 *
 * Limite: 10 MB (10240 KB). Somente arquivos PDF.
 */
class UploadPrescriptionPdfRequest extends FormRequest
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
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pdf.required' => 'O arquivo PDF é obrigatório.',
            'pdf.mimes' => 'Apenas arquivos PDF são aceitos.',
            'pdf.max' => 'O arquivo não pode exceder 10 MB.',
        ];
    }
}
