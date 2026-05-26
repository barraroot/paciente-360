<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Define células da matriz Persona × Canal (FR-009/010).
 * Co-tenancy garantida pela regra exists escopada ao tenant atual (G2).
 */
class StorePersonaChannelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ai.matrix.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app('tenant')->id;

        return [
            'cells' => ['required', 'array', 'min:1'],
            'cells.*.ai_persona_id' => [
                'required',
                'integer',
                Rule::exists('ai_personas', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'cells.*.channel_type' => ['required', 'string', 'in:whatsapp,instagram,web'],
            'cells.*.is_active' => ['required', 'boolean'],
        ];
    }
}
