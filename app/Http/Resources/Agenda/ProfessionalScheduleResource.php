<?php

namespace App\Http\Resources\Agenda;

use App\Models\Professional;
use App\Services\Agenda\TimezoneResolverService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T043 — Envelope da agenda do profissional (US-6.1).
 *
 * Retorna estrutura agregada com schedules + tipos aceitos + timezone resolvido.
 *
 * @mixin Professional
 */
class ProfessionalScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tzResolver = app(TimezoneResolverService::class);

        return [
            'professional_id' => $this->id,
            'timezone' => $this->timezone,
            'timezone_resolved' => $tzResolver->forProfessional($this->resource),
            'schedules' => $this->resource->schedules->map(fn ($schedule) => [
                'id' => $schedule->id,
                'day_of_week' => $schedule->day_of_week,
                'blocks' => $schedule->blocks,
                'effective_from' => $schedule->effective_from?->toDateString(),
                'effective_until' => $schedule->effective_until?->toDateString(),
            ])->values(),
            'accepted_appointment_type_ids' => $this->resource->appointmentTypes->pluck('id')->values(),
        ];
    }
}
