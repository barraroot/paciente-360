<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Form Request para criação de sessão Stripe Checkout (T185).
 *
 * Somente `admin-clinica` ou `financeiro` podem iniciar o checkout —
 * médicos e outros perfis recebem 403 (FR-013).
 */
final class CheckoutRequest extends FormRequest
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
            'plan_code' => ['required', 'string', 'exists:plans,code'],
            'professionals_quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_code.exists' => __('validation.exists'),
            'professionals_quantity.min' => __('validation.min.numeric', ['min' => 1]),
        ];
    }
}
