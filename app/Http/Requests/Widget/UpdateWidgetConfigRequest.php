<?php

namespace App\Http\Requests\Widget;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T213 — FormRequest para atualizar configuração do widget web.
 *
 * PUT /api/v1/inbox/widget-configs/{channel_id}
 * Autorização: ability `channel.connect` (mesma do ChannelPolicy).
 */
class UpdateWidgetConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('channel.connect') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'appearance' => ['sometimes', 'array'],
            'appearance.primary_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{3,6}$/'],
            'appearance.logo_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'appearance.position' => ['sometimes', 'string', 'in:bottom-right,bottom-left'],
            'appearance.button_label' => ['sometimes', 'string', 'max:50'],

            'initial_message' => ['sometimes', 'nullable', 'string', 'max:500'],

            'business_hours' => ['sometimes', 'array'],
            'business_hours.monday' => ['sometimes', 'nullable', 'string', 'regex:/^\d{2}:\d{2}-\d{2}:\d{2}$/'],
            'business_hours.tuesday' => ['sometimes', 'nullable', 'string', 'regex:/^\d{2}:\d{2}-\d{2}:\d{2}$/'],
            'business_hours.wednesday' => ['sometimes', 'nullable', 'string', 'regex:/^\d{2}:\d{2}-\d{2}:\d{2}$/'],
            'business_hours.thursday' => ['sometimes', 'nullable', 'string', 'regex:/^\d{2}:\d{2}-\d{2}:\d{2}$/'],
            'business_hours.friday' => ['sometimes', 'nullable', 'string', 'regex:/^\d{2}:\d{2}-\d{2}:\d{2}$/'],
            'business_hours.saturday' => ['sometimes', 'nullable', 'string', 'regex:/^\d{2}:\d{2}-\d{2}:\d{2}$/'],
            'business_hours.sunday' => ['sometimes', 'nullable', 'string', 'regex:/^\d{2}:\d{2}-\d{2}:\d{2}$/'],
            'business_hours.timezone' => ['sometimes', 'string', 'timezone'],

            'outside_hours_behavior' => ['sometimes', 'string', 'in:bloqueia,fila,normal'],
            'outside_hours_message' => ['sometimes', 'nullable', 'string', 'max:500'],

            'pre_chat_form' => ['sometimes', 'string', 'in:opcional,exigido_para_iniciar,exigido_para_enviar'],

            'allowed_origins' => ['sometimes', 'array'],
            'allowed_origins.*' => ['string', 'url', 'max:255'],
        ];
    }
}
