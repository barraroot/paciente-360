<?php

declare(strict_types=1);

namespace App\Http\Requests\Professionals;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * **T014 (Spec 012)** — Validação para edição de Professional.
 *
 * Imutável após criação:
 *   - user_id (FR-010 explícito — vínculo não pode mudar)
 *   - is_active (só via /activate ou DELETE, não via PUT)
 */
final class UpdateProfessionalRequest extends FormRequest
{
    private const COUNCIL_TYPES = ['CRM', 'CRO', 'COREN', 'CRP', 'OUTRO'];

    private const UFS = [
        'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT',
        'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('professional.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:3', 'max:150'],
            'council_type' => ['sometimes', 'required', Rule::in(self::COUNCIL_TYPES)],
            'council_type_other' => [
                'nullable',
                'string',
                'min:2',
                'max:50',
                Rule::requiredIf(fn () => $this->input('council_type') === 'OUTRO'),
            ],
            'council_number' => ['sometimes', 'required', 'string', 'min:5', 'max:20', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'council_state' => ['sometimes', 'required', 'string', 'size:2', Rule::in(self::UFS)],
            'especialidade' => ['nullable', 'string', 'max:100'],

            // Campos PROIBIDOS em update.
            'user_id' => ['prohibited'],
            'is_active' => ['prohibited'],
        ];
    }
}
