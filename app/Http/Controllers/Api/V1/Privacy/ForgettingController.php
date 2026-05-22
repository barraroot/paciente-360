<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Privacy;

use App\Domain\Privacy\Events\DireitoEsquecimentoSolicitado;
use App\Domain\Privacy\Models\ForgettingRequest;
use App\Domain\Privacy\Models\ForgettingStatus;
use App\Domain\Privacy\Services\ForgettingExecutor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Privacy\CreateForgettingRequestRequest;
use App\Http\Requests\Privacy\DenyForgettingRequest;
use App\Http\Requests\Privacy\ExecuteForgettingRequest;
use App\Http\Resources\Privacy\ForgettingRequestResource;
use App\Models\Paciente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

/**
 * **T052 (Fase 8 — Lote A US-13.2)** — API REST do Direito ao Esquecimento.
 *
 * Endpoints:
 *   - GET    /api/v1/privacy/forgetting-requests              → index
 *   - GET    /api/v1/privacy/forgetting-requests/{forgetting} → show
 *   - POST   /api/v1/privacy/forgetting-requests              → store
 *   - POST   /api/v1/privacy/forgetting-requests/{forgetting}/execute → execute (Princípio I)
 *   - POST   /api/v1/privacy/forgetting-requests/{forgetting}/deny    → deny
 */
class ForgettingController extends Controller
{
    public function __construct(
        private readonly ForgettingExecutor $executor,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ForgettingRequest::class);

        $query = ForgettingRequest::query()->orderByDesc('deadline_at');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($request->boolean('only_open')) {
            $query->open();
        }

        $perPage = min((int) $request->integer('per_page', 25), 100);

        return ForgettingRequestResource::collection($query->paginate($perPage));
    }

    public function show(ForgettingRequest $forgetting): ForgettingRequestResource
    {
        Gate::authorize('view', $forgetting);

        return new ForgettingRequestResource($forgetting);
    }

    public function store(CreateForgettingRequestRequest $request): JsonResponse
    {
        $data = $request->validated();
        /** @var Paciente $patient */
        $patient = Paciente::query()->findOrFail($data['patient_id']);

        $requestedAt = Carbon::now();
        $deadlineAt = $requestedAt->copy()->addDays(21); // ~15 dias úteis

        $forgetting = ForgettingRequest::query()->create([
            'tenant_id' => $patient->tenant_id,
            'patient_id' => $patient->id,
            'requested_at' => $requestedAt,
            'deadline_at' => $deadlineAt,
            'channel_of_request' => $data['channel_of_request'],
            'status' => ForgettingStatus::Open,
        ]);

        Event::dispatch(new DireitoEsquecimentoSolicitado(
            tenantId: $patient->tenant_id,
            patientId: $patient->id,
            forgettingRequestId: $forgetting->id,
            requestedAt: $requestedAt,
            deadlineAt: $deadlineAt,
            channelOfRequest: $data['channel_of_request'],
        ));

        return (new ForgettingRequestResource($forgetting))
            ->response()
            ->setStatusCode(201);
    }

    public function execute(ExecuteForgettingRequest $request, ForgettingRequest $forgetting): ForgettingRequestResource
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $executed = $this->executor->execute($forgetting, $user);

        return new ForgettingRequestResource($executed);
    }

    public function deny(DenyForgettingRequest $request, ForgettingRequest $forgetting): ForgettingRequestResource
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $denied = $this->executor->deny($forgetting, $user, $request->string('reason')->toString());

        return new ForgettingRequestResource($denied);
    }
}
