<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para PATCH /billing/ai-usage/hard-cap (T223).
 *
 * Autorização: apenas `admin-clinica` ou `financeiro`.
 * Validação: `hard_cap` é nullable|integer|min:0.
 */
final class HardCapRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->hasAnyRole(['admin-clinica', 'financeiro']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hard_cap' => ['present', 'nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hard_cap.min' => __('validation.min.numeric', ['attribute' => 'hard_cap', 'min' => 0]),
        ];
    }
}
