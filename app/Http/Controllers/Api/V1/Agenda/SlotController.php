<?php

namespace App\Http\Controllers\Api\V1\Agenda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\ListAvailableSlotsRequest;
use App\Http\Requests\Agenda\ReserveSlotRequest;
use App\Http\Resources\Agenda\SlotReservationResource;
use App\Http\Resources\Agenda\SlotResource;
use App\Models\Agenda\AppointmentType;
use App\Models\Agenda\SlotReservation;
use App\Models\Professional;
use App\Services\Agenda\SlotConflictException;
use App\Services\Agenda\SlotGeneratorService;
use App\Services\Agenda\SlotReservationService;
use App\Services\Agenda\TimezoneResolverService;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * T083 — SlotController (US-6.5 — IA Matricial consume).
 */
class SlotController extends Controller
{
    public function __construct(
        private readonly SlotGeneratorService $generator,
        private readonly SlotReservationService $reservations,
        private readonly TimezoneResolverService $tzResolver,
    ) {}

    public function listAvailable(ListAvailableSlotsRequest $request)
    {
        $professional = Professional::findOrFail($request->validated()['professional_id']);
        $type = AppointmentType::findOrFail($request->validated()['appointment_type_id']);
        $from = Carbon::parse($request->validated()['from']);
        $to = Carbon::parse($request->validated()['to']);

        $slots = $this->generator->generate($professional, $type, $from, $to);

        // Paginação manual sobre Collection
        $perPage = (int) ($request->validated()['per_page'] ?? 50);
        $page = (int) ($request->validated()['page'] ?? 1);
        $paged = $slots->forPage($page, $perPage)->values();

        return response()->json([
            'data' => SlotResource::collection($paged)->resolve(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $slots->count(),
                'last_page' => max(1, (int) ceil($slots->count() / $perPage)),
            ],
            'timezone_display' => $this->tzResolver->forProfessional($professional),
        ]);
    }

    public function reservar(ReserveSlotRequest $request, string $startsAt)
    {
        $data = $request->validated();
        $professional = Professional::findOrFail($data['professional_id']);

        try {
            $reservation = $this->reservations->reserve(
                $professional,
                Carbon::parse($startsAt),
                $data,
            );
        } catch (SlotConflictException $e) {
            return response()->json([
                'error' => 'slot_already_reserved',
                'holder_type' => $e->holderType,
                'expires_at' => $e->expiresAt?->toIso8601String(),
                'message' => $e->getMessage(),
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'data' => new SlotReservationResource($reservation),
        ], Response::HTTP_CREATED);
    }

    public function releaseReservation(SlotReservation $slotReservation)
    {
        // Libera reserva explícita (form fechado pelo cliente)
        $this->reservations->release($slotReservation, 'canceled');

        return response()->noContent();
    }
}
