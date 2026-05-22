<?php

declare(strict_types=1);

namespace App\Http\Requests\Privacy;

use App\Domain\Privacy\Models\ConsentFinalidade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * **T028 (Fase 8 — Lote A US-13.1)** — Validação para revogar consentimento manualmente.
 *
 * Q25: revogação por finalidade. Scope=channel preserva metadata sobre intent
 * do paciente; scope=all sinaliza revogação total na finalidade.
 */
class RevokeConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('privacy.view');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:pacientes,id'],
            'finalidade' => ['required', 'string', Rule::in(array_column(ConsentFinalidade::cases(), 'value'))],
            'channel' => ['required', 'string', 'max:50'],
            'scope' => ['nullable', 'string', Rule::in(['channel', 'all'])],
            'evidence_message_id' => ['nullable', 'integer'],
        ];
    }
}
