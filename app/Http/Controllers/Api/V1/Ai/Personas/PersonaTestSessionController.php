<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ai\Personas;

use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\Persona\Models\PersonaTestSession;
use App\Domain\Ai\Persona\Services\PersonaTestSessionService;
use App\Domain\Ai\Persona\Services\SandboxOutboundDispatcher;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\Personas\OpenPersonaTestSessionRequest;
use App\Http\Requests\Ai\Personas\SendPersonaTestMessageRequest;
use App\Http\Resources\Ai\PersonaTestSessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * **T178 (Fase 18 — US6)** — CRUD do chat sandbox de Persona.
 *
 * Permission: `ai.persona.test` (Foundational T036). Isolamento por admin
 * (FR-043): listagem só retorna sessões do `$request->user()`.
 *
 * @group IA — Persona Test Chat
 */
final class PersonaTestSessionController extends Controller
{
    public function __construct(
        private readonly PersonaTestSessionService $sessions,
        private readonly SandboxOutboundDispatcher $sandboxDispatcher,
    ) {}

    /**
     * Abre uma nova sessão de teste (auto-fecha sessão `open` anterior do
     * mesmo admin+persona com motivo `superseded`).
     *
     * @authenticated
     */
    public function store(OpenPersonaTestSessionRequest $request, AiPersona $persona): JsonResponse
    {
        Gate::authorize('ai.persona.test');

        if ($persona->tenant_id !== app('tenant')->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $useDraft = $request->boolean('use_draft');
        $draft = $useDraft ? (array) $request->input('persona_draft') : null;

        $result = $this->sessions->open(
            admin: $request->user(),
            persona: $persona,
            personaDraft: $draft,
        );

        return response()->json([
            'data' => [
                'id' => $result->session->id,
                'persona_id' => $result->session->persona_id,
                'persona_snapshot' => $result->session->persona_snapshot,
                'status' => $result->session->status,
                'mcp_token' => $result->plainTextToken,
                'echo_channel' => $result->echoChannel,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Lista sessões do admin autenticado (FR-043 — isolamento).
     *
     * @authenticated
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('ai.persona.test');

        $sessions = PersonaTestSession::query()
            ->where('tenant_id', app('tenant')->id)
            ->where('admin_user_id', request()->user()->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return PersonaTestSessionResource::collection($sessions);
    }

    /**
     * Envia mensagem do admin (atuando como paciente) no sandbox.
     *
     * @authenticated
     */
    public function sendMessage(SendPersonaTestMessageRequest $request, PersonaTestSession $session): JsonResponse
    {
        Gate::authorize('ai.persona.test');

        if ($session->tenant_id !== app('tenant')->id) {
            abort(Response::HTTP_NOT_FOUND);
        }
        if ($session->admin_user_id !== $request->user()->id) {
            abort(Response::HTTP_NOT_FOUND);
        }
        if ($session->status !== 'open') {
            return response()->json(['error' => 'session_closed'], Response::HTTP_GONE);
        }

        $body = (string) $request->validated('text');
        $message = $this->sandboxDispatcher->dispatchAdminMessage($session, $body);

        // NOTE: a resposta da IA é processada async pela mesma stack da Fase 17/18
        // (AiMessageProcessor), mas para sandbox isso fica fora do MVP da US6 —
        // o broadcast retorna a mensagem do admin; resposta da IA do sandbox é
        // enviada via `SandboxOutboundDispatcher::dispatchAiResponse()` quando o
        // job da IA detectar `Message.sandbox=true` (futura iteração).

        return response()->json([
            'data' => [
                'message_id' => $message->id,
                'sandbox' => true,
                'echo_channel' => "private-persona-test.{$session->id}",
            ],
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Fecha a sessão (revoga PAT — FR-051).
     *
     * @authenticated
     */
    public function close(PersonaTestSession $session): JsonResponse
    {
        Gate::authorize('ai.persona.test');

        if ($session->tenant_id !== app('tenant')->id || $session->admin_user_id !== request()->user()->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $closed = $this->sessions->close($session);

        return response()->json(['data' => (new PersonaTestSessionResource($closed))->toArray(request())]);
    }

    /**
     * Arquiva a sessão.
     *
     * @authenticated
     */
    public function archive(PersonaTestSession $session): JsonResponse
    {
        Gate::authorize('ai.persona.test');

        if ($session->tenant_id !== app('tenant')->id || $session->admin_user_id !== request()->user()->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $archived = $this->sessions->archive($session);

        return response()->json(['data' => (new PersonaTestSessionResource($archived))->toArray(request())]);
    }
}
