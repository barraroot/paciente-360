<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\Public\Concerns\ResolvesApiPublicTenant;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Public\PatientPublicResource;
use App\Models\Paciente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

/**
 * **T223 (Fase 8 — Lote D US-11.2)** — Pacientes via API pública.
 *
 * Endpoints:
 *   GET    /v1/patients         → index (paginado)
 *   GET    /v1/patients/{id}    → show
 *   POST   /v1/patients         → store (idempotente — Idempotency-Key)
 *   PATCH  /v1/patients/{id}    → update
 */
class PatientsController extends Controller
{
    use ResolvesApiPublicTenant;

    public function index(Request $request): AnonymousResourceCollection
    {
        $tenantId = $this->tenantId($request);

        $patients = Paciente::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 25));

        return PatientPublicResource::collection($patients);
    }

    public function show(Request $request, Paciente $patient): PatientPublicResource
    {
        $this->assertSameTenant($patient, $this->tenantId($request));

        return new PatientPublicResource($patient);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $idempotencyKey = $this->idempotencyKey($request);

        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:160'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
        ]);

        // Idempotência — NFR-9: mesma resposta em 24h.
        if ($idempotencyKey !== null) {
            $cacheKey = "api_public:idempotency:{$tenantId}:patients:store:{$idempotencyKey}";
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return response()->json($cached, 201)->withHeaders(['Idempotency-Replayed' => 'true']);
            }
        }

        $patient = Paciente::query()->create($validated + ['tenant_id' => $tenantId]);

        $response = (new PatientPublicResource($patient))->resolve();

        if ($idempotencyKey !== null) {
            Cache::put($cacheKey ?? '', $response, now()->addHours(24));
        }

        return response()->json(['data' => $response], 201);
    }

    public function update(Request $request, Paciente $patient): PatientPublicResource
    {
        $this->assertSameTenant($patient, $this->tenantId($request));

        $patient->update($request->validate([
            'nome' => ['sometimes', 'string', 'max:160'],
            'telefone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:160'],
        ]));

        return new PatientPublicResource($patient);
    }

    private function assertSameTenant(Paciente $patient, int $tenantId): void
    {
        if ((int) $patient->tenant_id !== $tenantId) {
            abort(404);
        }
    }
}
