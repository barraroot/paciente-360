<?php

declare(strict_types=1);

namespace App\Http\Requests\Campaigns;

use App\Domain\Campaigns\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * **T164 (Fase 8 — Lote C US-9.1)** — Disparar campanha (AC-9.1.2).
 */
class DispatchCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Campaign|null $campaign */
        $campaign = $this->route('campaign');

        return $campaign !== null && Gate::allows('dispatch', $campaign);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'accepted'],
        ];
    }
}
