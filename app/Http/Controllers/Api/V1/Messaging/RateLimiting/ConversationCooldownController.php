<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messaging\RateLimiting;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\RateLimiting\CooldownService;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ConversationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * **T205 (Fase 18 — Polish, FR-008b)** — operador encerra manualmente o
 * cooldown anti-abuso de uma conversa.
 *
 * Permission: `messaging.cooldown.manage` (atribuída a admin-clinica e
 * atendente por default). Tenant-scoped pelo middleware `tenant.slug`.
 *
 * @group Inbox — Cooldown
 */
final class ConversationCooldownController extends Controller
{
    public function __construct(
        private readonly CooldownService $cooldown,
    ) {}

    /**
     * Encerra o cooldown anti-abuso de uma conversa (operador).
     * Idempotente — retorna estado atual sem erro se já não estava em cooldown.
     *
     * @authenticated
     */
    public function end(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('messaging.cooldown.manage');

        if ($conversation->tenant_id !== app('tenant')->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if ($conversation->cooldown_until === null) {
            // Idempotência — não estava em cooldown, devolve estado atual sem erro.
            return response()->json([
                'data' => (new ConversationResource($conversation->fresh()))->toArray($request),
            ]);
        }

        $this->cooldown->endBy($conversation, $request->user());

        return response()->json([
            'data' => (new ConversationResource($conversation->fresh()))->toArray($request),
        ]);
    }
}
