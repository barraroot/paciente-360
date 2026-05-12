<?php

namespace App\Http\Controllers\Api\V1\Inbox;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\QuickReply\Models\QuickReply;
use App\Domain\Messaging\QuickReply\Services\QuickReplyService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inbox\CreateQuickReplyRequest;
use App\Http\Requests\Inbox\RenderQuickReplyRequest;
use App\Http\Requests\Inbox\UpdateQuickReplyRequest;
use App\Http\Resources\V1\QuickReplyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * T237 — Controller para respostas rápidas (US-4.7).
 *
 * Pipeline: FormRequest → Controller (thin) → QuickReplyService → Resource.
 *
 * Endpoints:
 *  - GET    /inbox/quick-replies              → index
 *  - POST   /inbox/quick-replies              → store
 *  - PATCH  /inbox/quick-replies/{quickReply} → update
 *  - DELETE /inbox/quick-replies/{quickReply} → destroy
 *  - POST   /inbox/quick-replies/{quickReply}/render → render
 */
final class QuickRepliesController extends Controller
{
    /**
     * GET /inbox/quick-replies
     *
     * Lista respostas rápidas visíveis para o usuário (tenant + próprias privadas).
     * Private overrides tenant quando shortcut coincide.
     */
    public function index(Request $request, QuickReplyService $service): JsonResponse
    {
        $user = $request->user();
        $scope = $request->input('scope', 'all');
        $search = $request->input('q');
        $sort = $request->input('sort');

        $replies = $service->listVisible($user, $scope, $search, $sort);

        return response()->json([
            'data' => QuickReplyResource::collection($replies),
        ]);
    }

    /**
     * POST /inbox/quick-replies
     *
     * Cria resposta rápida (scope=tenant exige quick_reply.manage;
     * scope=private qualquer inbox.respond).
     */
    public function store(CreateQuickReplyRequest $request, QuickReplyService $service): JsonResponse
    {
        $reply = $service->create(
            user: $request->user(),
            scope: $request->string('scope')->toString(),
            shortcut: $request->string('shortcut')->toString(),
            content: $request->string('content')->toString(),
        );

        return response()->json(['data' => new QuickReplyResource($reply)], 201);
    }

    /**
     * PATCH /inbox/quick-replies/{quickReply}
     *
     * Atualiza resposta rápida (policy: tenant-scope exige quick_reply.manage; privada = owner).
     */
    public function update(
        UpdateQuickReplyRequest $request,
        QuickReplyService $service,
        QuickReply $quickReply,
    ): JsonResponse {
        Gate::authorize('update', $quickReply);

        $reply = $service->update($quickReply, $request->only(['shortcut', 'content']));

        return response()->json(['data' => new QuickReplyResource($reply)]);
    }

    /**
     * DELETE /inbox/quick-replies/{quickReply}
     *
     * Remove resposta rápida (policy: tenant-scope exige quick_reply.manage; privada = owner).
     */
    public function destroy(QuickReplyService $service, QuickReply $quickReply): JsonResponse
    {
        Gate::authorize('delete', $quickReply);

        $service->delete($quickReply);

        return response()->json(null, 204);
    }

    /**
     * POST /inbox/quick-replies/{quickReply}/render
     *
     * Renderiza variáveis em contexto de uma conversa para preview server-side.
     */
    public function render(
        RenderQuickReplyRequest $request,
        QuickReplyService $service,
        QuickReply $quickReply,
    ): JsonResponse {
        Gate::authorize('render', $quickReply);

        $conversation = Conversation::findOrFail($request->integer('conversation_id'));

        $result = $service->render($quickReply, $conversation, $request->user());

        return response()->json($result);
    }
}
