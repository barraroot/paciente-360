<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Domain\Prescription\Prescription\Prescription;
use App\Http\Controllers\Api\V1\Public\Concerns\ResolvesApiPublicTenant;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Public\PrescriptionPublicResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * **T226 (Fase 8 — Lote D US-11.2 + R-8-4)** — Prescriptions via API pública.
 *
 * Read-only. Controladas mascaradas pelo Resource SEMPRE — independente
 * do scope do token (defense em profundidade).
 */
class PrescriptionsController extends Controller
{
    use ResolvesApiPublicTenant;

    public function index(Request $request): AnonymousResourceCollection
    {
        $tenantId = $this->tenantId($request);

        return PrescriptionPublicResource::collection(
            Prescription::query()
                ->where('tenant_id', $tenantId)
                ->orderByDesc('created_at')
                ->paginate((int) $request->integer('per_page', 25)),
        );
    }

    public function show(Request $request, Prescription $prescription): PrescriptionPublicResource
    {
        if ((int) $prescription->tenant_id !== $this->tenantId($request)) {
            abort(404);
        }

        return new PrescriptionPublicResource($prescription);
    }
}
