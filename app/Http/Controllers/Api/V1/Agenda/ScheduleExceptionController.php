<?php

namespace App\Http\Controllers\Api\V1\Agenda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\StoreScheduleExceptionRequest;
use App\Http\Resources\Agenda\ScheduleExceptionResource;
use App\Models\Agenda\ScheduleException;
use App\Models\Professional;
use App\Policies\Agenda\ProfessionalSchedulePolicy;
use App\Services\Agenda\ScheduleConfigurationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * T045 — Endpoints de bloqueios de agenda (US-6.1, clarify nº 5).
 */
class ScheduleExceptionController extends Controller
{
    public function __construct(private readonly ScheduleConfigurationService $service) {}

    public function index(Professional $professional, Request $request)
    {
        $query = ScheduleException::query()
            ->where('professional_id', $professional->id)
            ->orderBy('starts_at');

        if ($from = $request->query('from')) {
            $query->where('ends_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('starts_at', '<=', $to);
        }

        return ScheduleExceptionResource::collection($query->get());
    }

    public function store(StoreScheduleExceptionRequest $request, Professional $professional)
    {
        $result = $this->service->createException(
            $professional,
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'data' => new ScheduleExceptionResource($result['exception']),
            'cascaded_cancellations' => $result['cascaded_cancellations'],
        ], 201);
    }

    public function destroy(ScheduleException $scheduleException)
    {
        // Policy: usar a mesma do professional (delegação simples)
        if (! (new ProfessionalSchedulePolicy)->update(
            request()->user(),
            $scheduleException->professional
        )) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $scheduleException->delete();

        return response()->noContent();
    }
}
