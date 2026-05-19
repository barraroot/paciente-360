<?php

namespace App\Http\Requests\Prescriptions;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * T109 — FormRequest para atualização de configuração de alertas de receita.
 *
 * Regras:
 *  - `alert_disabled = true` apenas em receitas do tipo `common` (AC-8.2.2).
 *  - `prescription_alert.configure` ability obrigatória.
 */
class UpdateAlertConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Prescription|null $prescription */
        $prescription = $this->route('prescription');

        if ($prescription === null) {
            return false;
        }

        return Gate::allows('update', $prescription)
            && $this->user()?->can('prescription_alert.configure') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alert_disabled' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            /** @var Prescription|null $prescription */
            $prescription = $this->route('prescription');

            if ($prescription === null) {
                return;
            }

            $alertDisabled = $this->boolean('alert_disabled');

            if ($alertDisabled && $prescription->type !== PrescriptionType::Common) {
                $v->errors()->add(
                    'alert_disabled',
                    'Não é possível desabilitar alertas em receitas do tipo special ou controlled.',
                );
            }
        });
    }
}
