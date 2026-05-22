<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Privacy;

use App\Domain\Privacy\Events\PortabilidadeDadosSolicitada;
use App\Domain\Privacy\Models\PortabilityRequest;
use App\Domain\Privacy\Models\PortabilityStatus;
use App\Domain\Privacy\Services\PortabilityExporter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Privacy\CreatePortabilityRequestRequest;
use App\Http\Resources\Privacy\PortabilityRequestResource;
use App\Models\Paciente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

/**
 * **T053 (Fase 8 — Lote A US-13.2)** — API REST de Portabilidade de Dados (Q28).
 *
 * Endpoints autenticados (auth:sanctum + tenant.slug):
 *   - GET  /api/v1/privacy/portability-requests                  → index
 *   - GET  /api/v1/privacy/portability-requests/{portability}    → show
 *   - POST /api/v1/privacy/portability-requests                  → store
 *   - POST /api/v1/privacy/portability-requests/{portability}/execute → gera arquivo
 *
 * O endpoint público de download por `file_signed_url_id` fica em
 * {@see PortabilityPublicDownloadController} (sem auth, validação UUID).
 */
class PortabilityController extends Controller
{
    public function __construct(
        private readonly PortabilityExporter $exporter,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PortabilityRequest::class);

        $query = PortabilityRequest::query()->orderByDesc('requested_at');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $perPage = min((int) $request->integer('per_page', 25), 100);

        return PortabilityRequestResource::collection($query->paginate($perPage));
    }

    public function show(PortabilityRequest $portability): PortabilityRequestResource
    {
        Gate::authorize('view', $portability);

        return new PortabilityRequestResource($portability);
    }

    public function store(CreatePortabilityRequestRequest $request): JsonResponse
    {
        $data = $request->validated();
        /** @var Paciente $patient */
        $patient = Paciente::query()->findOrFail($data['patient_id']);

        $requestedAt = Carbon::now();
        $deadlineAt = $requestedAt->copy()->addDays(21);

        $portability = PortabilityRequest::query()->create([
            'tenant_id' => $patient->tenant_id,
            'patient_id' => $patient->id,
            'requested_at' => $requestedAt,
            'deadline_at' => $deadlineAt,
            'status' => PortabilityStatus::Open,
        ]);

        Event::dispatch(new PortabilidadeDadosSolicitada(
            tenantId: $patient->tenant_id,
            patientId: $patient->id,
            portabilityRequestId: $portability->id,
            requestedAt: $requestedAt,
            deadlineAt: $deadlineAt,
        ));

        return (new PortabilityRequestResource($portability))
            ->response()
            ->setStatusCode(201);
    }

    public function execute(Request $request, PortabilityRequest $portability): PortabilityRequestResource
    {
        Gate::authorize('execute', $portability);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $ready = $this->exporter->execute($portability, $user);

        return new PortabilityRequestResource($ready);
    }
}
