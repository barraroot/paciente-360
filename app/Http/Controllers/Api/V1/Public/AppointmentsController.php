<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\Public\Concerns\ResolvesApiPublicTenant;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Public\AppointmentPublicResource;
use App\Models\Agenda\Appointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * **T224 (Fase 8 — Lote D US-11.2)** — Appointments via API pública.
 */
class AppointmentsController extends Controller
{
    use ResolvesApiPublicTenant;

    public function index(Request $request): AnonymousResourceCollection
    {
        $tenantId = $this->tenantId($request);

        return AppointmentPublicResource::collection(
            Appointment::query()
                ->where('tenant_id', $tenantId)
                ->orderByDesc('starts_at')
                ->paginate((int) $request->integer('per_page', 25)),
        );
    }

    public function show(Request $request, Appointment $appointment): AppointmentPublicResource
    {
        $this->assertSameTenant($appointment, $this->tenantId($request));

        return new AppointmentPublicResource($appointment);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $idempotencyKey = $this->idempotencyKey($request);

        $validated = $request->validate([
            'patient_id' => ['required', 'integer'],
            'professional_id' => ['required', 'integer'],
            'appointment_type_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date'],
        ]);

        if ($idempotencyKey !== null) {
            $cacheKey = "api_public:idempotency:{$tenantId}:appointments:store:{$idempotencyKey}";
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return response()->json($cached, 201)->withHeaders(['Idempotency-Replayed' => 'true']);
            }
        }

        // Delegação ao service interno seria ideal — placeholder direto para MVP.
        $appointment = Appointment::query()->create([
            'tenant_id' => $tenantId,
            'paciente_id' => $validated['patient_id'],
            'professional_id' => $validated['professional_id'],
            'appointment_type_id' => $validated['appointment_type_id'],
            'starts_at' => $validated['starts_at'],
            'ends_at' => Carbon::parse($validated['starts_at'])->addMinutes(30),
            'status' => 'scheduled',
        ]);

        $response = (new AppointmentPublicResource($appointment))->resolve();

        if ($idempotencyKey !== null) {
            Cache::put($cacheKey ?? '', $response, now()->addHours(24));
        }

        return response()->json(['data' => $response], 201);
    }

    public function update(Request $request, Appointment $appointment): AppointmentPublicResource
    {
        $this->assertSameTenant($appointment, $this->tenantId($request));

        $appointment->update($request->validate([
            'starts_at' => ['sometimes', 'date'],
            'status' => ['sometimes', 'string'],
        ]));

        return new AppointmentPublicResource($appointment);
    }

    public function destroy(Request $request, Appointment $appointment): JsonResponse
    {
        $this->assertSameTenant($appointment, $this->tenantId($request));

        $appointment->update(['status' => 'canceled']);

        return response()->json(['message' => 'canceled']);
    }

    private function assertSameTenant(Appointment $appointment, int $tenantId): void
    {
        if ((int) $appointment->tenant_id !== $tenantId) {
            abort(404);
        }
    }
}
