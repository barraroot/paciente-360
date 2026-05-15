<?php

namespace App\Http\Controllers\Api\V1\Agenda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\StoreAppointmentTypeRequest;
use App\Http\Requests\Agenda\UpdateAppointmentTypeRequest;
use App\Http\Resources\Agenda\AppointmentTypeResource;
use App\Models\Agenda\AppointmentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * T055 — CRUD de tipos de atendimento (US-6.2).
 *
 * Destroy faz soft inactivate (FR-007 — preserva histórico).
 */
class AppointmentTypeController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', AppointmentType::class);

        $query = AppointmentType::query()->orderBy('nome');

        if (! $request->boolean('include_inactive')) {
            $query->active();
        }

        return AppointmentTypeResource::collection($query->paginate((int) $request->query('per_page', 50)));
    }

    public function store(StoreAppointmentTypeRequest $request)
    {
        $type = AppointmentType::create($request->validated() + ['slug' => $request->input('slug')]);

        return new AppointmentTypeResource($type);
    }

    public function show(AppointmentType $appointmentType)
    {
        Gate::authorize('view', $appointmentType);

        return new AppointmentTypeResource($appointmentType);
    }

    public function update(UpdateAppointmentTypeRequest $request, AppointmentType $appointmentType)
    {
        $appointmentType->update($request->validated());

        return new AppointmentTypeResource($appointmentType->fresh());
    }

    public function destroy(AppointmentType $appointmentType)
    {
        Gate::authorize('delete', $appointmentType);

        // Soft inactivate (FR-007 — preserva histórico)
        $appointmentType->update(['is_active' => false]);

        return response()->noContent();
    }
}
