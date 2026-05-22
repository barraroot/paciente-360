<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Privacy;

use App\Domain\Privacy\Models\ConsentFinalidade;
use App\Domain\Privacy\Models\ConsentRecord;
use App\Domain\Privacy\Services\ConsentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Privacy\RecordConsentRequest;
use App\Http\Requests\Privacy\RevokeConsentRequest;
use App\Http\Resources\Privacy\ConsentRecordResource;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * **T029 (Fase 8 — Lote A US-13.1)** — API REST de consentimentos.
 *
 * Endpoints expostos:
 *   - GET  /api/v1/privacy/consents              → listagem paginada
 *   - GET  /api/v1/privacy/consents/{consent}    → ficha de 1 consentimento
 *   - POST /api/v1/privacy/consents              → registrar (granted ou refused)
 *   - POST /api/v1/privacy/consents/revoke       → revogar por (patient, finalidade)
 *
 * Pipeline obrigatório: FormRequest → Controller → Service → Resource
 * (Princípio constituição §466 — Controller fino, regra de negócio no Service).
 */
class ConsentsController extends Controller
{
    public function __construct(
        private readonly ConsentService $consentService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ConsentRecord::class);

        $query = ConsentRecord::query()
            ->orderByDesc('created_at');

        if ($patientId = $request->integer('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        if ($finalidade = $request->string('finalidade')->toString()) {
            $query->where('finalidade', $finalidade);
        }

        if ($state = $request->string('state')->toString()) {
            $query->where('state', $state);
        }

        if ($channel = $request->string('channel')->toString()) {
            $query->where('channel', $channel);
        }

        $perPage = min((int) $request->integer('per_page', 25), 100);

        return ConsentRecordResource::collection($query->paginate($perPage));
    }

    public function show(ConsentRecord $consent): ConsentRecordResource
    {
        Gate::authorize('view', $consent);

        return new ConsentRecordResource($consent);
    }

    public function store(RecordConsentRequest $request): JsonResponse
    {
        Gate::authorize('create', ConsentRecord::class);

        $data = $request->validated();

        /** @var Paciente $patient */
        $patient = Paciente::query()->findOrFail($data['patient_id']);

        $finalidade = ConsentFinalidade::from($data['finalidade']);

        $record = $data['state'] === 'granted'
            ? $this->consentService->record(
                patient: $patient,
                channel: $data['channel'],
                finalidade: $finalidade,
                evidenceMessageId: $data['evidence_message_id'] ?? null,
                evidenceSnapshot: $data['evidence_snapshot'] ?? null,
                termsVersion: $data['terms_version'] ?? '1.0',
            )
            : $this->consentService->refuse(
                patient: $patient,
                channel: $data['channel'],
                finalidade: $finalidade,
                evidenceMessageId: $data['evidence_message_id'] ?? null,
                evidenceSnapshot: $data['evidence_snapshot'] ?? null,
                termsVersion: $data['terms_version'] ?? '1.0',
            );

        return (new ConsentRecordResource($record))
            ->response()
            ->setStatusCode(201);
    }

    public function revoke(RevokeConsentRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->can('privacy.view')) {
            abort(403);
        }

        $data = $request->validated();

        /** @var Paciente $patient */
        $patient = Paciente::query()->findOrFail($data['patient_id']);

        $revoked = $this->consentService->revoke(
            patient: $patient,
            finalidade: ConsentFinalidade::from($data['finalidade']),
            channel: $data['channel'],
            evidenceMessageId: $data['evidence_message_id'] ?? null,
            scope: $data['scope'] ?? 'all',
        );

        return response()->json([
            'revoked_count' => count($revoked),
            'consents' => ConsentRecordResource::collection(collect($revoked)),
        ]);
    }
}
