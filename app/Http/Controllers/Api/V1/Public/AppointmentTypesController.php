<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\Public\Concerns\ResolvesApiPublicTenant;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Public\AppointmentTypePublicResource;
use App\Models\Agenda\AppointmentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * **T227 (Fase 8 — Lote D US-11.2)** — Tipos de atendimento (read-only).
 */
class AppointmentTypesController extends Controller
{
    use ResolvesApiPublicTenant;

    public function index(Request $request): AnonymousResourceCollection
    {
        $tenantId = $this->tenantId($request);

        return AppointmentTypePublicResource::collection(
            AppointmentType::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->paginate(100),
        );
    }

    public function show(Request $request, AppointmentType $appointmentType): AppointmentTypePublicResource
    {
        if ((int) $appointmentType->tenant_id !== $this->tenantId($request)) {
            abort(404);
        }

        return new AppointmentTypePublicResource($appointmentType);
    }
}
