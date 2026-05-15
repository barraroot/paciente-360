<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T134b — POST /agenda/waitlist/{id}/aceitar (US-6.6).
 *
 * Sem ability check — IA Matricial pode chamar pelo paciente. A validação
 * real é entry.status='notified' + expires_at no futuro (no service).
 */
class AcceptWaitlistOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }
}
