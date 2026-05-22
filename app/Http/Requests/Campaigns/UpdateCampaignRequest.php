<?php

declare(strict_types=1);

namespace App\Http\Requests\Campaigns;

use App\Domain\Campaigns\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * **T164 (Fase 8 — Lote C US-9.1)** — Editar campanha (apenas se status=scheduled).
 *
 * Campanha em dispatching/completed/canceled é imutável (AC-9.2.3).
 */
class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Campaign|null $campaign */
        $campaign = $this->route('campaign');

        return $campaign !== null
            && Gate::allows('update', $campaign);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'template_id' => ['sometimes', 'nullable', 'integer', 'exists:messaging_channel_templates,id'],
            'scheduled_for' => ['sometimes', 'nullable', 'date', 'after:now'],
            'audience_filters' => ['sometimes', 'array'],
        ];
    }
}
