<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\Public\Concerns\ResolvesApiPublicTenant;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Public\ProfessionalPublicResource;
use App\Models\Professional;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * **T228 (Fase 8 — Lote D US-11.2)** — Profissionais (read-only).
 */
class ProfessionalsController extends Controller
{
    use ResolvesApiPublicTenant;

    public function index(Request $request): AnonymousResourceCollection
    {
        $tenantId = $this->tenantId($request);

        return ProfessionalPublicResource::collection(
            Professional::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->paginate(100),
        );
    }

    public function show(Request $request, Professional $professional): ProfessionalPublicResource
    {
        if ((int) $professional->tenant_id !== $this->tenantId($request)) {
            abort(404);
        }

        return new ProfessionalPublicResource($professional);
    }
}
