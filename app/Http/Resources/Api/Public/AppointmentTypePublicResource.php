<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Public;

use App\Models\Agenda\AppointmentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AppointmentType
 */
class AppointmentTypePublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'duration_minutes' => $this->duration_minutes,
            'price_cents' => $this->price_cents,
            'color' => $this->color,
            'is_active' => $this->is_active,
        ];
    }
}
