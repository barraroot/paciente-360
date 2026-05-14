<?php

namespace App\Http\Controllers\Api\V1\Agenda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\AcceptWaitlistOfferRequest;
use App\Http\Requests\Agenda\StoreWaitlistEntryRequest;
use App\Http\Resources\Agenda\AppointmentResource;
use App\Http\Resources\Agenda\WaitlistEntryResource;
use App\Models\Agenda\AppointmentType;
use App\Models\Agenda\WaitlistEntry;
use App\Models\Paciente;
use App\Models\Professional;
use App\Services\Agenda\WaitlistService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * T136 — WaitlistController (US-6.6 — clarify nº 8).
 */
class WaitlistController extends Controller
{
    public function __construct(private readonly WaitlistService $service) {}

    public function index(Request $request)
    {
        $query = WaitlistEntry::query()->orderBy('position');

        foreach (['professional_id', 'appointment_type_id', 'status'] as $field) {
            if ($value = $request->query($field)) {
                $query->where($field, $value);
            }
        }

        return WaitlistEntryResource::collection($query->paginate((int) $request->query('per_page', 50)));
    }

    public function store(StoreWaitlistEntryRequest $request)
    {
        $data = $request->validated();
        $entry = $this->service->enroll(
            Paciente::findOrFail($data['paciente_id']),
            Professional::findOrFail($data['professional_id']),
            AppointmentType::findOrFail($data['appointment_type_id']),
        );

        return new WaitlistEntryResource($entry);
    }

    public function destroy(WaitlistEntry $waitlist)
    {
        $this->service->cancel($waitlist);

        return response()->noContent();
    }

    public function accept(AcceptWaitlistOfferRequest $request, WaitlistEntry $waitlist)
    {
        try {
            $appointment = $this->service->accept(
                $waitlist,
                $request->validated(),
                $request->user(),
            );
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'data' => new WaitlistEntryResource($waitlist->fresh()),
            'appointment' => new AppointmentResource($appointment),
        ]);
    }
}
