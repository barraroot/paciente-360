<?php

namespace App\Http\Controllers\Api\V1\Agenda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\RescheduleAppointmentRequest;
use App\Http\Requests\Agenda\StoreAppointmentRequest;
use App\Http\Resources\Agenda\AppointmentResource;
use App\Models\Agenda\Appointment;
use App\Services\Agenda\AppointmentService;
use App\Services\Agenda\SlotConflictException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * T082 — AppointmentController (US-6.3 + US-6.5 reagendar).
 *
 * Cancel/markAttendance ficam em US4/US5 (próximos lotes).
 */
class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $service) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Appointment::class);

        $query = Appointment::query()->orderBy('starts_at');

        foreach (['professional_id', 'paciente_id'] as $field) {
            if ($value = $request->query($field)) {
                $query->where($field, $value);
            }
        }

        if ($status = $request->query('status')) {
            $query->whereIn('status', is_array($status) ? $status : [$status]);
        }

        if ($from = $request->query('from')) {
            $query->where('starts_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('starts_at', '<=', $to);
        }

        return AppointmentResource::collection(
            $query->paginate((int) $request->query('per_page', 50))
        );
    }

    public function store(StoreAppointmentRequest $request)
    {
        try {
            $result = $this->service->create($request->validated(), $request->user());
        } catch (SlotConflictException $e) {
            return response()->json(['error' => 'slot_conflict'], Response::HTTP_CONFLICT);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'data' => new AppointmentResource($result['appointment']),
            'idempotent_replay' => $result['idempotent_replay'],
        ], $result['idempotent_replay'] ? Response::HTTP_OK : Response::HTTP_CREATED);
    }

    public function show(Appointment $appointment)
    {
        Gate::authorize('view', $appointment);

        return new AppointmentResource($appointment);
    }

    public function reschedule(RescheduleAppointmentRequest $request, Appointment $appointment)
    {
        try {
            $updated = $this->service->reschedule($appointment, $request->validated(), $request->user());
        } catch (SlotConflictException $e) {
            return response()->json(['error' => 'slot_conflict'], Response::HTTP_CONFLICT);
        } catch (\DomainException $e) {
            $payload = json_decode($e->getMessage(), true);
            if (is_array($payload) && isset($payload['error'])) {
                return response()->json($payload, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return response()->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new AppointmentResource($updated);
    }
}
