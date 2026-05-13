<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T110 — POST /agenda/consultas/{id}/confirmar-resposta (clarify nº 6).
 *
 * Endpoint INTERNO chamado pela Fase 3 quando paciente responde via canal.
 * Auth via Bearer (mesmo middleware das demais rotas), mas aceita atendente
 * (que tem appointment.update) ou IA (futuro: token system com ability dedicada).
 */
class ConfirmResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('appointment.update');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'response_value' => ['required', 'in:1,2,3'],
            'dispatch_kind' => ['required', 'in:24h,2h,retry_30min,via_ia'],
            'received_at' => ['nullable', 'date'],
        ];
    }
}
