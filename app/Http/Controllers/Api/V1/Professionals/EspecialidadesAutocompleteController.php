<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Professionals;

use App\Http\Controllers\Controller;
use App\Models\Professional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * **T064 (Spec 012)** — GET /api/v1/professionals/especialidades?q=...
 *
 * Autocomplete contra histórico do tenant (Q1 / FR-001 / R7).
 * Retorna DISTINCT especialidades já cadastradas no tenant. ILIKE
 * case-insensitive quando `q` informado.
 */
final class EspecialidadesAutocompleteController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('professional.manage');

        $term = trim($request->string('q')->value());
        $limit = $term === '' ? 50 : 10;

        $query = Professional::query()
            ->whereNotNull('especialidade')
            ->where('especialidade', '!=', '');

        if ($term !== '') {
            $query->whereRaw('LOWER(especialidade) LIKE ?', ['%'.mb_strtolower($term).'%']);
        }

        $list = $query
            ->orderBy('especialidade')
            ->distinct()
            ->limit($limit)
            ->pluck('especialidade')
            ->values()
            ->all();

        return response()->json(['data' => $list]);
    }
}
