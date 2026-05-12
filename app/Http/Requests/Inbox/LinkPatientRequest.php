<?php

namespace App\Http\Requests\Inbox;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest para vincular conversa a um paciente.
 */
final class LinkPatientRequest extends FormRequest
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
        $tenantId = app('tenant')?->id;

        return [
            'patient_id' => [
                'required',
                'integer',
                "exists:pacientes,id,tenant_id,{$tenantId}",
            ],
        ];
    }
}
