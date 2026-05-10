<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para completar um step do wizard de onboarding.
 *
 * O payload é livre (additionalProperties: true no OpenAPI) — a API aceita
 * qualquer JSON object como body para permitir que cada step envie seus
 * próprios dados (ex.: endereço, horários). A validação de conteúdo de cada
 * step é responsabilidade de camadas futuras quando as etapas forem ativadas.
 *
 * A autorização (role `admin-clinica`) é feita no controller via Policy.
 *
 * @see App\Http\Controllers\Api\V1\Onboarding\OnboardingController
 * @see specs/001-fundacao-multitenant/contracts/openapi.yaml § OnboardingStepRequest
 */
final class OnboardingStepRequest extends FormRequest
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
        return [];
    }
}
