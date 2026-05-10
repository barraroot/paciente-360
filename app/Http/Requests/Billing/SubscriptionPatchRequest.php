<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

/**
 * Form Request para atualização de assinatura ativa — upgrade/downgrade (T202).
 *
 * Somente `admin-clinica` ou `financeiro` podem alterar a assinatura.
 * Pelo menos `plan_code` ou `professionals_quantity` deve ser informado.
 */
final class SubscriptionPatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole('admin-clinica') || $user->hasRole('financeiro');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_code' => ['sometimes', 'string', 'exists:plans,code'],
            'professionals_quantity' => ['sometimes', 'integer', 'min:1'],
            'proration_behavior' => ['sometimes', 'in:create_prorations,none'],
        ];
    }

    /**
     * Validação adicional: ao menos um campo de mudança deve estar presente.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $hasPlanCode = $this->has('plan_code');
            $hasQuantity = $this->has('professionals_quantity');

            if (! $hasPlanCode && ! $hasQuantity) {
                $v->errors()->add(
                    'plan_code',
                    __('validation.required_without', [
                        'attribute' => 'plan_code',
                        'values' => 'professionals_quantity',
                    ])
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_code.exists' => __('validation.exists'),
            'professionals_quantity.min' => __('validation.min.numeric', ['min' => 1]),
            'proration_behavior.in' => __('validation.in'),
        ];
    }
}
