<?php

declare(strict_types=1);

namespace App\Http\Requests\Privacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * **T050 (Fase 8 — Lote A US-13.2)** — Solicitação de Direito ao Esquecimento.
 *
 * Endpoint usado por (a) Admin Clínica em painel autenticado, (b) formulário
 * público sem auth (rota web separada). A authorize() retorna true porque
 * a separação de quem pode criar fica nas rotas (com/sem middleware).
 */
class CreateForgettingRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:pacientes,id'],
            'channel_of_request' => [
                'required',
                'string',
                Rule::in(['form', 'whatsapp', 'instagram', 'web', 'email', 'phone', 'manual']),
            ],
            'verification_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
