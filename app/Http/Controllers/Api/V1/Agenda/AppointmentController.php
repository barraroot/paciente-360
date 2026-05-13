<?php

namespace App\Http\Controllers\Api\V1\Agenda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\ConfirmResponseRequest;
use App\Http\Requests\Agenda\MarkAttendanceRequest;
use App\Http\Requests\Agenda\RescheduleAppointmentRequest;
use App\Http\Requests\Agenda\StoreAppointmentRequest;
use App\Http\Resources\Agenda\AppointmentResource;
use App\Models\Agenda\Appointment;
use App\Services\Agenda\AppointmentService;
use App\Services\Agenda\AttendanceMarkingService;
use App\Services\Agenda\ConfirmationResponseProcessor;
use App\Services\Agenda\SlotConflictException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
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

    /**
     * T112a — Ingesta resposta de confirmação vinda da Fase 3 (US-6.4).
     */
    public function confirmResponse(
        ConfirmResponseRequest $request,
        Appointment $appointment,
        ConfirmationResponseProcessor $processor,
    ) {
        Gate::authorize('view', $appointment);

        $data = $request->validated();
        $updated = $processor->process(
            $appointment,
            $data['response_value'],
            $data['dispatch_kind'],
            isset($data['received_at']) ? Carbon::parse($data['received_at']) : null,
        );

        return new AppointmentResource($updated);
    }

    /**
     * T112b — Marcar comparecimento (clarify nº 14).
     */
    public function markAttendance(
        MarkAttendanceRequest $request,
        Appointment $appointment,
        AttendanceMarkingService $service,
    ) {
        try {
            $updated = $service->mark(
                $appointment,
                $request->validated()['status'],
                $request->validated()['attendance_motivo'] ?? null,
                $request->user(),
            );
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new AppointmentResource($updated);
    }

    /**
     * T112c — Reverter marcação (clarify nº 14).
     */
    public function revertAttendance(
        Request $request,
        Appointment $appointment,
        AttendanceMarkingService $service,
    ) {
        Gate::authorize('update', $appointment);

        try {
            $updated = $service->revert($appointment, $request->user());
        } catch (\DomainException $e) {
            $code = $e->getMessage() === 'revert_window_expired'
                ? Response::HTTP_FORBIDDEN
                : Response::HTTP_UNPROCESSABLE_ENTITY;

            return response()->json(['error' => $e->getMessage()], $code);
        }

        return new AppointmentResource($updated);
    }
}
