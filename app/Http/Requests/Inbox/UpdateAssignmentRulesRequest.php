<?php

namespace App\Http\Requests\Inbox;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * T152 — FormRequest para PUT /inbox/assignment-rules (replace full set).
 *
 * Requer role `admin-clinica` (inbox.assign ability).
 * Valida cada regra: strategy enum, priority int, is_active bool, config array, channel_id nullable.
 */
final class UpdateAssignmentRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Apenas admin-clinica pode modificar regras de atribuição automática.
        // Atendentes e outros papéis com inbox.assign não têm acesso a esta configuração.
        return $this->user()?->hasRole('admin-clinica') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rules' => ['required', 'array'],
            'rules.*.strategy' => ['required', 'string', Rule::in(['round_robin', 'patient_owner', 'manual'])],
            'rules.*.priority' => ['required', 'integer', 'min:1'],
            'rules.*.is_active' => ['required', 'boolean'],
            'rules.*.config' => ['nullable', 'array'],
            'rules.*.channel_id' => ['nullable', 'integer', 'exists:messaging_channels,id'],
        ];
    }
}
