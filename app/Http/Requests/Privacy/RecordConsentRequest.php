<?php

declare(strict_types=1);

namespace App\Http\Requests\Privacy;

use App\Domain\Privacy\Models\ConsentFinalidade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * **T028 (Fase 8 — Lote A US-13.1)** — Validação para registrar consentimento manualmente.
 *
 * Usado pelo painel Admin Clínica quando recebe consentimento por canal não-automatizado
 * (telefone, papel, e-mail) e precisa lavrar o registro estruturado.
 */
class RecordConsentRequest extends FormRequest
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
            'channel' => ['required', 'string', 'max:50', Rule::in(['whatsapp', 'instagram', 'web', 'form', 'manual', 'phone', 'email'])],
            'finalidade' => ['required', 'string', Rule::in(array_column(ConsentFinalidade::cases(), 'value'))],
            'state' => ['required', 'string', Rule::in(['granted', 'refused'])],
            'evidence_message_id' => ['nullable', 'integer'],
            'evidence_snapshot' => ['nullable', 'array'],
            'evidence_snapshot.text' => ['required_with:evidence_snapshot', 'string', 'max:2000'],
            'terms_version' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.exists' => 'Paciente não encontrado neste tenant.',
            'finalidade.in' => 'Finalidade deve ser uma das: transacional, marketing, pesquisa.',
            'state.in' => 'Estado deve ser granted ou refused (use endpoint específico para revogar).',
        ];
    }
}
