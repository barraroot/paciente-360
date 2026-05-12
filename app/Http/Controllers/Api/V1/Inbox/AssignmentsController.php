<?php

namespace App\Http\Controllers\Api\V1\Inbox;

use App\Domain\Messaging\Assignment\Exceptions\CrossTenantAssignmentException;
use App\Domain\Messaging\Assignment\Exceptions\UserAtMaxLimitException;
use App\Domain\Messaging\Assignment\Services\AssignmentService;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Conversation\Models\ConversationAssignment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inbox\AssignConversationRequest;
use App\Http\Requests\Inbox\TransferConversationRequest;
use App\Http\Resources\V1\AssignmentResource;
use App\Http\Resources\V1\ConversationResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * T150 — Controller para assign, transfer e histórico de assignments.
 *
 * Pipeline: FormRequest → Controller (thin) → AssignmentService → Resource.
 * Autorização via AssignmentPolicy + FormRequest (ability check).
 */
final class AssignmentsController extends Controller
{
    /**
     * POST /inbox/conversations/{conversation}/assign
     *
     * Atribuição manual (`user_id`) ou auto (`auto: true`).
     */
    public function assign(
        AssignConversationRequest $request,
        AssignmentService $service,
        Conversation $conversation,
    ): JsonResponse {
        Gate::authorize('assign', $conversation);

        try {
            if ($request->boolean('auto')) {
                $strategy = $request->input('strategy');
                $updated = $service->assignAuto($conversation, $request->user(), $strategy);
            } else {
                $userId = $request->integer('user_id');
                $target = User::where('id', $userId)->first();

                if ($target === null) {
                    return response()->json(['error' => 'user_not_found'], 404);
                }

                // Cross-tenant validation — guard before reaching service
                if ($target->tenant_id !== $conversation->tenant_id) {
                    $this->recordCrossTenantAudit($conversation, $target);

                    return response()->json(['error' => 'cross_tenant_blocked'], 403);
                }

                $updated = $service->assignManual($conversation, $target, $request->user());
            }
        } catch (CrossTenantAssignmentException) {
            return response()->json(['error' => 'cross_tenant_blocked'], 403);
        } catch (UserAtMaxLimitException) {
            return response()->json(['error' => 'user_at_max_limit'], 422);
        }

        $updated->loadMissing(['channel', 'patient', 'assignedUser']);

        return response()->json(['data' => new ConversationResource($updated)]);
    }

    /**
     * POST /inbox/conversations/{conversation}/transfer
     *
     * Transferência para usuário (`user_id`) ou role (`role`).
     */
    public function transfer(
        TransferConversationRequest $request,
        AssignmentService $service,
        Conversation $conversation,
    ): JsonResponse {
        Gate::authorize('transfer', $conversation);

        $note = $request->string('transfer_note')->toString();

        try {
            if ($request->filled('role')) {
                $updated = $service->transferToRole(
                    conversation: $conversation,
                    role: $request->string('role')->toString(),
                    by: $request->user(),
                    note: $note,
                );
            } else {
                $userId = $request->integer('user_id');
                $target = User::where('id', $userId)->first();

                if ($target === null) {
                    return response()->json(['error' => 'user_not_found'], 404);
                }

                // Cross-tenant validation
                if ($target->tenant_id !== $conversation->tenant_id) {
                    $this->recordCrossTenantAudit($conversation, $target);

                    return response()->json(['error' => 'cross_tenant_blocked'], 403);
                }

                $updated = $service->transferToUser(
                    conversation: $conversation,
                    target: $target,
                    by: $request->user(),
                    note: $note,
                );
            }
        } catch (CrossTenantAssignmentException) {
            return response()->json(['error' => 'cross_tenant_blocked'], 403);
        }

        $updated->loadMissing(['channel', 'patient', 'assignedUser']);

        return response()->json(['data' => new ConversationResource($updated)]);
    }

    /**
     * GET /inbox/conversations/{conversation}/assignments
     *
     * Histórico cronológico de atribuições da conversa (DESC).
     */
    public function assignments(Conversation $conversation): JsonResponse
    {
        Gate::authorize('viewAssignments', $conversation);

        $assignments = ConversationAssignment::where('conversation_id', $conversation->id)
            ->where('tenant_id', $conversation->tenant_id)
            ->with('user', 'assignedBy')
            ->orderByDesc('assigned_at')
            ->get();

        return response()->json([
            'data' => AssignmentResource::collection($assignments),
        ]);
    }

    /**
     * Records a cross-tenant attempt in audit_logs.
     */
    private function recordCrossTenantAudit(Conversation $conversation, User $targetUser): void
    {
        try {
            AuditLog::create([
                'tenant_id' => $conversation->tenant_id,
                'user_id' => auth()->id(),
                'actor_type' => 'user',
                'action' => 'conversa.transferencia.cross_tenant_bloqueado',
                'auditable_type' => Conversation::class,
                'auditable_id' => $conversation->id,
                'payload' => [
                    'conversation_id' => $conversation->id,
                    'target_user_id' => $targetUser->id,
                    'target_user_tenant_id' => $targetUser->tenant_id,
                ],
            ]);
        } catch (\Throwable) {
            // Never let audit failure block the 403 response
        }
    }
}
