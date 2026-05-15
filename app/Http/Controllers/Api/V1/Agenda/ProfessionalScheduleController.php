<?php

namespace App\Http\Controllers\Api\V1\Agenda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\UpdateProfessionalScheduleRequest;
use App\Http\Resources\Agenda\ProfessionalScheduleResource;
use App\Models\Professional;
use App\Services\Agenda\ScheduleConfigurationService;
use Illuminate\Support\Facades\Gate;

/**
 * T044 — Endpoints de agenda recorrente (US-6.1).
 */
class ProfessionalScheduleController extends Controller
{
    public function __construct(private readonly ScheduleConfigurationService $service) {}

    public function show(Professional $professional)
    {
        Gate::authorize('view', $professional);

        $professional->load(['schedules', 'appointmentTypes']);

        return new ProfessionalScheduleResource($professional);
    }

    public function update(UpdateProfessionalScheduleRequest $request, Professional $professional)
    {
        $result = $this->service->updateSchedule(
            $professional,
            $request->validated(),
            $request->user(),
        );

        $result['professional']->load(['schedules', 'appointmentTypes']);

        return new ProfessionalScheduleResource($result['professional']);
    }
}
