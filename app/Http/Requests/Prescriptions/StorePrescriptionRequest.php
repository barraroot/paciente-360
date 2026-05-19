<?php

namespace App\Http\Requests\Prescriptions;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * T060 — Validação de criação de receita.
 *
 * Regras regulatórias enforçadas:
 *  - `controlled` → exatamente 1 item.
 *  - `common` → `duration_days` ∈ {30,60,90,180}.
 *  - `alert_disabled` apenas para `common` (AC-8.2.2).
 *  - Campos server-side (`expires_at`, `status`, `source`, `professional_id`)
 *    são ignorados — não fazem parte das rules, então se enviados não passam
 *    para os dados validados.
 */
class StorePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Prescription::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->input('type');

        return [
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('pacientes', 'id')->where('tenant_id', app('tenant')?->id),
            ],
            'type' => ['required', Rule::enum(PrescriptionType::class)],
            'appointment_id' => [
                'nullable',
                'integer',
                Rule::exists('appointments', 'id')->where('tenant_id', app('tenant')?->id),
            ],
            'issued_at' => ['required', 'date'],
            'duration_days' => $type === 'common'
                ? ['required', 'integer', Rule::in([30, 60, 90, 180])]
                : ['nullable', 'integer'],
            'alert_disabled' => $type === 'common'
                ? ['nullable', 'boolean']
                : ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:10'],
            'items.*.medication_name' => ['required', 'string', 'max:255'],
            'items.*.posology' => ['required', 'string'],
            'items.*.concentration' => ['nullable', 'string', 'max:100'],
            'items.*.pharmaceutical_form' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['nullable', 'string', 'max:100'],
            'items.*.treatment_duration' => ['nullable', 'string', 'max:100'],
            // T142 — renewed_from_id: vínculo com receita original (US-8.3).
            // Sem `exists` aqui — validação cross-tenant feita no service para
            // evitar timing attack e vazamento de existência via erro.
            'renewed_from_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.min' => 'A receita deve ter pelo menos 1 medicamento.',
            'items.max' => 'A receita pode ter no máximo 10 medicamentos.',
            'duration_days.in' => 'A duração de receita comum deve ser 30, 60, 90 ou 180 dias.',
            'alert_disabled.declined' => 'Alerta de vencimento é obrigatório para receitas especiais e controladas.',
        ];
    }

    /**
     * @throws ValidationException
     */
    protected function passedValidation(): void
    {
        $type = $this->input('type');

        // Gate Portaria 344/98: receita controlada deve ter exatamente 1 item.
        if ($type === 'controlled') {
            $items = $this->input('items', []);
            if (count($items) !== 1) {
                throw ValidationException::withMessages([
                    'items' => 'Receita controlada deve ter exatamente 1 medicamento (Portaria 344/98).',
                ]);
            }
        }

        // AC-8.2.2: alert_disabled só permitido para receita comum.
        if (in_array($type, ['special', 'controlled'], true) && $this->boolean('alert_disabled')) {
            throw ValidationException::withMessages([
                'alert_disabled' => 'Alerta de vencimento é obrigatório para receitas especiais e controladas.',
            ]);
        }
    }
}
