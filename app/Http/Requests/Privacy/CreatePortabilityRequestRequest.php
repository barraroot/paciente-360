<?php

declare(strict_types=1);

namespace App\Http\Requests\Privacy;

use Illuminate\Foundation\Http\FormRequest;

/**
 * **T051 (Fase 8 — Lote A US-13.2)** — Solicitação de Portabilidade de Dados (Q28).
 */
class CreatePortabilityRequestRequest extends FormRequest
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
        ];
    }
}
