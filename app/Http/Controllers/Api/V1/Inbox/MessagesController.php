<?php

namespace App\Http\Controllers\Api\V1\Inbox;

use App\Domain\Messaging\Channel\Adapters\OutboundMessage;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Exceptions\WindowExpiredWithoutTemplateException;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Message\Services\MessageDispatchService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inbox\SendMessageRequest;
use App\Http\Resources\V1\MessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * T115 — Controller de mensagens de uma conversa.
 *
 * Pipeline: FormRequest → Controller (fino) → Service → Resource.
 * Autorização via ConversationPolicy.
 */
final class MessagesController extends Controller
{
    /**
     * GET /inbox/conversations/{conversation}/messages
     * Lista mensagens cursor-paginadas (DESC).
     */
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeConversationAccess('view', $conversation);

        $perPage = 50;
        $cursor = $request->query('cursor');

        $query = Message::with('media')
            ->where('tenant_id', $conversation->tenant_id)
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        // Cursor: "created_at|id"
        if ($cursor !== null && $cursor !== '') {
            $parts = explode('|', (string) $cursor, 2);

            if (count($parts) === 2) {
                [$cursorDate, $cursorId] = $parts;
                $query->where(function ($q) use ($cursorDate, $cursorId): void {
                    $q->where('created_at', '<', $cursorDate)
                        ->orWhere(fn ($q2) => $q2->where('created_at', '=', $cursorDate)->where('id', '<', (int) $cursorId));
                });
            }
        }

        $messages = $query->limit($perPage + 1)->get();
        $hasMore = $messages->count() > $perPage;

        if ($hasMore) {
            $messages = $messages->take($perPage);
        }

        $nextCursor = null;

        if ($hasMore) {
            $last = $messages->last();
            $nextCursor = $last?->created_at?->toIso8601String().'|'.$last?->id;
        }

        return response()->json([
            'data' => MessageResource::collection($messages),
            'meta' => [
                'next_cursor' => $nextCursor,
                'has_more' => $hasMore,
            ],
        ]);
    }

    /**
     * POST /inbox/conversations/{conversation}/messages
     * Envia mensagem outbound.
     */
    public function store(
        SendMessageRequest $request,
        MessageDispatchService $dispatch,
        Conversation $conversation,
    ): JsonResponse {
        $this->authorizeConversationAccess('respond', $conversation);

        $idempotencyKey = (string) $request->input('idempotency_key', '');
        $contentType = (string) $request->input('content_type');
        $body = $request->input('body');
        $templateData = $request->input('template', []);

        $outbound = new OutboundMessage(
            conversationExternalThreadId: $conversation->external_thread_id,
            contentType: $contentType,
            body: $body,
            templateProviderId: $templateData['provider_template_id'] ?? null,
            templateVariables: $templateData['variables'] ?? [],
            media: null,
            idempotencyKey: $idempotencyKey,
        );

        // Idempotency check: se já existe, retorna 200
        if ($idempotencyKey !== '') {
            $existing = Message::withoutGlobalScopes()
                ->where('tenant_id', $conversation->tenant_id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                // Body mismatch → 422
                if ($existing->body !== $body) {
                    return response()->json([
                        'error' => 'idempotency_conflict',
                        'message' => 'Idempotency key já utilizada com corpo diferente.',
                    ], 422);
                }

                return (new MessageResource($existing))->response()->setStatusCode(200);
            }
        }

        try {
            $message = $dispatch->send($conversation, $outbound, $request->user()?->id);

            Log::info('message.store', [
                'tenant_id' => $conversation->tenant_id,
                'user_id' => $request->user()?->id,
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
            ]);

            return (new MessageResource($message))->response()->setStatusCode(201);
        } catch (WindowExpiredWithoutTemplateException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'mensagem.bloqueada_fora_janela',
                'details' => [
                    'last_inbound_message_at' => $e->lastInboundMessageAt?->toIso8601String(),
                    'hours_since_last_inbound' => $e->hoursSinceLastInbound,
                    'available_templates_count' => $e->availableTemplatesCount,
                ],
            ], 422);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Idempotency key conflict')) {
                return response()->json([
                    'error' => 'idempotency_conflict',
                    'message' => 'Idempotency key já utilizada com corpo diferente.',
                ], 422);
            }

            throw $e;
        }
    }

    /**
     * Authorizes a conversation-scoped gate ability.
     *
     * For users with the 'medico' role, authorization failure returns HTTP 404
     * (privacy by design). For all other roles, returns 403 Forbidden.
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
