<?php

declare(strict_types=1);

namespace App\Http\Requests\Campaigns;

use App\Domain\Campaigns\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CancelCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Campaign|null $campaign */
        $campaign = $this->route('campaign');

        return $campaign !== null && Gate::allows('cancel', $campaign);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
