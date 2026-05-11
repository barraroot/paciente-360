<?php

namespace App\Http\Controllers\Api\V1\Pacientes;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TimelineResource;
use App\Models\Paciente;
use App\Services\Pacientes\TimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * T154 — Controller de timeline do paciente (read-only).
 *
 * Invocado via `GET /api/v1/pacientes/{id}/timeline`.
 * Delega toda a lógica de filtro, cursor e visibilidade ao `TimelineService`.
 *
 * @group CRM Pacientes
 *
 * @see App\Services\Pacientes\TimelineService
 */
final class TimelineController extends Controller
{
    public function __invoke(
        int $id,
        Request $request,
        TimelineService $service,
    ): JsonResponse {
        $paciente = Paciente::findOrFail($id);

        Gate::authorize('view', $paciente);

        $tipos = $request->input('tipo', []);
        if (is_string($tipos) && $tipos !== '') {
            $tipos = explode(',', $tipos);
        }

        $filters = ['tipo' => (array) $tipos];

        $result = $service->forPaciente(
            paciente: $paciente,
            filters: $filters,
            cursor: $request->input('cursor'),
            limit: min((int) $request->input('limit', 50), 200),
        );

        return (new TimelineResource($result))->response();
    }
}
