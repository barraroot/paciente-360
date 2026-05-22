<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Public;

use App\Models\Professional;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Professional
 */
class ProfessionalPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'specialty' => $this->specialty,
            'is_active' => $this->is_active,
        ];
    }
}
