<?php

declare(strict_types=1);

namespace App\Http\Requests\Campaigns;

use App\Domain\Campaigns\Models\CampaignChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * **T164 (Fase 8 — Lote C US-9.1)** — Validação de criação de campanha.
 */
class CreateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('campaign.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'channel' => ['required', 'string', Rule::in(array_column(CampaignChannel::cases(), 'value'))],
            'template_id' => ['nullable', 'integer', 'exists:messaging_channel_templates,id'],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
            'audience_filters' => ['required', 'array'],
            'audience_filters.inactivity_months' => ['nullable', 'integer', 'min:1', 'max:60'],
            'audience_filters.tags' => ['nullable', 'array'],
            'audience_filters.tags.*' => ['string', 'max:50'],
            'audience_filters.last_professional_id' => ['nullable', 'integer'],
            'audience_filters.age_range' => ['nullable', 'array'],
            'audience_filters.age_range.min' => ['nullable', 'integer', 'min:0', 'max:120'],
            'audience_filters.age_range.max' => ['nullable', 'integer', 'min:0', 'max:120'],
            'audience_filters.gender' => ['nullable', 'string', Rule::in(['M', 'F', 'O'])],
        ];
    }
}
