<?php

namespace App\Http\Requests\Prescriptions;

use App\Domain\Prescription\Renewal\InitiatedByType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * T137 — Validação de requisição de renovação de receita (US-8.3).
 *
 * Regras:
 *  - `initiated_by` obrigatório e dentro do enum.
 *  - `appointment_id` opcional — se informado, deve existir e pertencer ao mesmo tenant.
 *
 * Autorização: o controller faz Gate check na prescription; aqui retornamos true.
 */
class RenewPrescriptionRequest extends FormRequest
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
            'initiated_by' => [
                'required',
                'string',
                Rule::in(array_column(InitiatedByType::cases(), 'value')),
            ],
            'appointment_id' => [
                'nullable',
                'integer',
                Rule::exists('appointments', 'id'),
            ],
        ];
    }

    /**
     * Validação cross-tenant para appointment_id.
     *
     * Defesa em profundidade: o controller também valida, mas FormRequest
     * é o ponto primário de validação.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $appointmentId = $this->integer('appointment_id', 0) ?: null;

            if ($appointmentId === null) {
                return;
            }

            $tenantId = app('tenant')?->id;

            if ($tenantId === null) {
                return;
            }

            $exists = DB::table('appointments')
                ->where('id', $appointmentId)
                ->where('tenant_id', $tenantId)
                ->exists();

            if (! $exists) {
                $v->errors()->add('appointment_id', 'The appointment does not belong to the current tenant.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'initiated_by.required' => 'O tipo de iniciador da renovação é obrigatório.',
            'initiated_by.in' => 'O iniciador deve ser: professional, ai ou patient.',
        ];
    }
}
