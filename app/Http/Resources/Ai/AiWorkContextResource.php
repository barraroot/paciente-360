<?php

declare(strict_types=1);

namespace App\Http\Resources\Ai;

use App\Domain\Ai\WorkContext\Models\AiWorkContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiWorkContext
 */
class AiWorkContextResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'services' => $this->services ?? [],
            'pricing' => $this->pricing ?? [],
            'locations' => $this->locations ?? [],
            'deposit_policy' => $this->deposit_policy ?? (object) [],
            'tone' => $this->tone,
            'qualification_questions' => $this->qualification_questions ?? [],
            'free_form' => $this->free_form,
            'version' => $this->version,
            'is_active' => $this->is_active ?? true,
            'updated_at' => $this->updated_at,
        ];
    }
}
