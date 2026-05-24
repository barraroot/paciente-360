<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Professionals;

use App\Http\Controllers\Controller;
use App\Http\Resources\Professionals\ProfessionalResource;
use App\Models\Professional;
use App\Services\Professionals\ProfessionalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * **T035 (Spec 012)** — POST /api/v1/professionals/{professional}/activate
 *
 * Reativa profissional soft-deletado. Pacientes já reatribuídos NÃO retornam.
 */
final class ProfessionalActivateController extends Controller
{
    public function __invoke(Request $request, int $professionalId, ProfessionalService $service): ProfessionalResource
    {
        $professional = Professional::withTrashed()->findOrFail($professionalId);

        Gate::authorize('professional.manage');

        $activated = $service->activate($professional, $request->user());

        return ProfessionalResource::make($activated);
    }
}
