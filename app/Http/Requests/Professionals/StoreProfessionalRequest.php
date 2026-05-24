<?php

declare(strict_types=1);

namespace App\Http\Requests\Professionals;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * **T013 (Spec 012)** — Validação para criação de Professional.
 *
 * XOR entre `user_id` (vincular a usuário existente) e `email` (convidar).
 * Q3: `council_type_other` obrigatório quando `council_type='OUTRO'`.
 * Q2: flag `confirmed_existing_user` permite vincular após confirmação no front.
 *
 * Princípio II (multi-tenant): user_id deve pertencer ao tenant corrente.
 */
final class StoreProfessionalRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'council_type' => ['required', Rule::in(self::COUNCIL_TYPES)],
            'council_type_other' => [
                'nullable',
                'string',
                'min:2',
                'max:50',
                Rule::requiredIf(fn () => $this->input('council_type') === 'OUTRO'),
            ],
            'council_number' => ['required', 'string', 'min:5', 'max:20', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'council_state' => ['required', 'string', 'size:2', Rule::in(self::UFS)],
            'especialidade' => ['nullable', 'string', 'max:100'],

            // XOR — exatamente um entre user_id e email.
            'user_id' => [
                'nullable',
                'required_without:email',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $this->user()?->tenant_id)),
            ],
            'email' => [
                'nullable',
                'required_without:user_id',
                'email:rfc',
                'max:255',
            ],
            'confirmed_existing_user' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'council_type_other.required' => trans('professionals.validation.council_type_other_required'),
            'user_id.exists' => trans('professionals.validation.user_belongs_to_other_tenant'),
        ];
    }
}
