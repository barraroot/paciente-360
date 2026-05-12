<?php

namespace App\Http\Controllers\Api\V1\Inbox;

use App\Domain\Messaging\Conversation\Exceptions\InvalidConversationTransitionException;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Conversation\Services\ConversationService;
use App\Domain\Messaging\Message\Services\MessageSearchService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inbox\LinkPatientRequest;
use App\Http\Requests\Inbox\ListConversationsRequest;
use App\Http\Resources\V1\ConversationResource;
use App\Models\Paciente;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * T114 — Controller de conversas do inbox.
 *
 * Pipeline: FormRequest → Controller (fino) → Service → Resource.
 * Autorização via ConversationPolicy + Gate.
 */
final class ConversationsController extends Controller
{
    /**
     * GET /inbox/conversations
     * Lista conversas do tenant com filtros e aggregations.
     */
    public function index(ListConversationsRequest $request, MessageSearchService $searchService): JsonResponse
    {
        Gate::authorize('viewAny', Conversation::class);

        $tenant = app('tenant');
        $user = $request->user();

        $query = Conversation::with(['channel', 'patient', 'assignedUser'])
            ->where('tenant_id', $tenant->id);

        // Médico: escopo restrito — vê apenas conversas atribuídas a si
        // OU conversas cujo paciente tem seu Professional como responsável
        if ($user->hasRole('medico')) {
            $query->where(function ($q) use ($user): void {
                $q->where('assigned_user_id', $user->id)
                    ->orWhereHas('patient.profissionalResponsavel', fn ($pq) => $pq->where('user_id', $user->id));
            });
        }

        // Filtro status (multi-select)
        if ($request->filled('status')) {
            $query->whereIn('status', (array) $request->input('status'));
        }

        // Filtro canal
        if ($request->filled('channel_id')) {
            $query->where('channel_id', (int) $request->input('channel_id'));
        }

        if ($request->filled('channel_type')) {
            $query->whereHas('channel', fn ($cq) => $cq->where('type', $request->input('channel_type')));
        }

        // Filtro atendente
        if ($request->filled('assigned_user_id')) {
            $assignedUserId = $request->input('assigned_user_id');

            if ($assignedUserId === 'me') {
                $query->where('assigned_user_id', $user->id);
            } elseif ($assignedUserId === 'unassigned') {
                $query->whereNull('assigned_user_id');
            } else {
                $query->where('assigned_user_id', (int) $assignedUserId);
            }
        }

        // Filtro paciente
        if ($request->filled('patient_id')) {
            $query->where('patient_id', (int) $request->input('patient_id'));
        }

        // Filtro IA pausada
        if ($request->filled('ai_paused')) {
            $aiPaused = filter_var($request->input('ai_paused'), FILTER_VALIDATE_BOOLEAN);

            if ($aiPaused === true) {
                $query->where('ai_paused_until', '>', now());
            }
        }

        // Filtro atividade — toIso8601String() usa '+' que URL-encode decodifica como espaço
        if ($request->filled('last_activity_from')) {
            try {
                $raw = str_replace(' ', '+', (string) $request->input('last_activity_from'));
                $from = Carbon::parse($raw);
                $query->where('last_message_at', '>=', $from->toDateTimeString());
            } catch (\Throwable) {
                // ignora data inválida
            }
        }

        if ($request->filled('last_activity_to')) {
            try {
                $raw = str_replace(' ', '+', (string) $request->input('last_activity_to'));
                $to = Carbon::parse($raw);
                $query->where('last_message_at', '<=', $to->toDateTimeString());
            } catch (\Throwable) {
                // ignora data inválida
            }
        }

        // Busca trigram
        if ($request->filled('q')) {
            $conversationIds = $searchService->searchConversationIds($tenant->id, (string) $request->input('q'));

            if (empty($conversationIds)) {
                $query->whereRaw('1 = 0'); // nenhum resultado
            } else {
                $query->whereIn('id', $conversationIds);
            }
        }

        $query->orderByDesc('last_message_at');

        $perPage = (int) ($request->input('per_page', 25));
        $paginated = $query->paginate($perPage);

        // Aggregations para badges (sobre o tenant inteiro, sem filtros de médico)
        $baseAgg = Conversation::where('tenant_id', $tenant->id);
        $byStatus = $baseAgg->clone()->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $aggregations = [
            'by_status' => [
                'aberta' => $byStatus->get('aberta', 0),
                'pendente' => $byStatus->get('pendente', 0),
                'resolvida' => $byStatus->get('resolvida', 0),
                'reaberta' => $byStatus->get('reaberta', 0),
            ],
            'unassigned' => (int) Conversation::where('tenant_id', $tenant->id)->whereNull('assigned_user_id')->count(),
            'mine' => (int) Conversation::where('tenant_id', $tenant->id)->where('assigned_user_id', $user->id)->count(),
        ];

        return response()->json([
            'data' => ConversationResource::collection($paginated->items()),
            'meta' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                ...$aggregations,
            ],
        ]);
    }

    /**
     * GET /inbox/conversations/{conversation}
     *
     * Returns 404 (instead of 403) when the user is a medico who cannot see the conversation.
     * This hides existence of the record (privacy by design).
     */
    public function show(Request $request, Conversation $conversation): ConversationResource
    {
        $this->authorizeConversationAccess('view', $conversation);

        $conversation->loadMissing(['channel', 'patient', 'assignedUser']);

        return new ConversationResource($conversation);
    }

    /**
     * POST /inbox/conversations/{conversation}/resolve
     */
    public function resolve(Request $request, Conversation $conversation, ConversationService $service): JsonResponse
    {
        $this->authorizeConversationAccess('resolve', $conversation);

        try {
            $resolved = $service->resolve($conversation, 'manual', $request->user()?->id);

            $resolved->loadMissing(['channel', 'patient', 'assignedUser']);

            Log::info('conversation.resolved', [
                'tenant_id' => $conversation->tenant_id,
                'conversation_id' => $conversation->id,
                'user_id' => $request->user()?->id,
            ]);

            return (new ConversationResource($resolved))->response()->setStatusCode(200);
        } catch (InvalidConversationTransitionException $e) {
            return response()->json([
                'error' => 'invalid_transition',
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    /**
     * POST /inbox/conversations/{conversation}/reopen
     */
    public function reopen(Request $request, Conversation $conversation, ConversationService $service): JsonResponse
    {
        $this->authorizeConversationAccess('reopen', $conversation);

        try {
            $reopened = $service->reopen($conversation, 'manual');

            $reopened->loadMissing(['channel', 'patient', 'assignedUser']);

            return (new ConversationResource($reopened))->response()->setStatusCode(200);
        } catch (InvalidConversationTransitionException $e) {
            return response()->json([
                'error' => 'invalid_transition',
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    /**
     * POST /inbox/conversations/{conversation}/link-patient
     */
    public function linkPatient(
        LinkPatientRequest $request,
        Conversation $conversation,
        ConversationService $service,
    ): ConversationResource {
        $this->authorizeConversationAccess('linkPatient', $conversation);

        $patient = Paciente::findOrFail($request->integer('patient_id'));
        $linked = $service->linkToPatient($conversation, $patient, $request->user()?->id);

        $linked->loadMissing(['channel', 'patient', 'assignedUser']);

        return new ConversationResource($linked);
    }

    /**
     * POST /inbox/conversations/{conversation}/read
     */
    public function read(Request $request, Conversation $conversation, ConversationService $service): Response
    {
        $this->authorizeConversationAccess('read', $conversation);

        $service->markAsRead($conversation, $request->user());

        return response()->noContent();
    }

    /**
     * Authorizes a conversation-scoped gate ability.
     *
     * For users with the 'medico' role, an authorization failure returns HTTP 404
     * (privacy by design — the conversation must not be revealed to exist).
     * For all other roles, a standard 403 Forbidden is returned.
     */
    private function authorizeConversationAccess(string $ability, Conversation $conversation): void
    {
        $user = request()->user();

        if (! Gate::check($ability, $conversation)) {
            if ($user !== null && $user->hasRole('medico')) {
                abort(404);
            }

            abort(403);
        }
    }
}
