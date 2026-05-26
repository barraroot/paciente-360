<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Domain\Ai\Assignment\Services\AiConversationAssignmentService;
use App\Domain\Ai\Matrix\Services\AiMatrixService;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * US6 — controle humano da IA na conversa (FR-016/SC-007).
 *
 * Estado, pausa manual indefinida e reativação. Autorização por `inbox.respond`
 * (mesma capacidade de responder a conversa).
 */
class AiConversationController extends Controller
{
    public function __construct(
        private readonly AiConversationAssignmentService $assignments,
        private readonly AiMatrixService $matrix,
    ) {}

    public function state(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('inbox.respond');

        return response()->json(['data' => $this->statePayload($conversation)]);
    }

    public function pause(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('inbox.respond');

        $this->assignments->pauseIndefinitely($conversation, $request->user()?->id);

        return response()->json(['data' => $this->statePayload($conversation->refresh())]);
    }

    public function resume(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('inbox.respond');

        if ($conversation->status === 'resolvida') {
            return response()->json(['message' => __('ai.conversation.already_closed')], 422);
        }

        $this->assignments->resume($conversation, $request->user()?->id);

        return response()->json(['data' => $this->statePayload($conversation->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function statePayload(Conversation $conversation): array
    {
        $conversation->loadMissing('channel');
        $channelType = $conversation->channel?->type;

        $assignment = $this->assignments->findActive($conversation->id);
        $assignment?->loadMissing('persona');

        $paused = $conversation->ai_paused_until !== null && $conversation->ai_paused_until->isFuture();

        return [
            'conversation_id' => $conversation->id,
            'channel_type' => $channelType,
            'ai_enabled' => $channelType !== null
                && $this->matrix->isChannelAiEnabled($conversation->tenant_id, $channelType),
            'paused' => $paused,
            'paused_until' => $conversation->ai_paused_until?->toIso8601String(),
            'assignment' => $assignment === null ? null : [
                'status' => $assignment->status,
                'persona' => $assignment->persona === null ? null : [
                    'id' => $assignment->persona->id,
                    'name' => $assignment->persona->name,
                ],
                'paused_at' => $assignment->paused_at?->toIso8601String(),
            ],
        ];
    }
}
