<?php

namespace App\Http\Controllers\Api\V1\Inbox;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ConversationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * T121 — Long-polling fallback para clientes sem suporte a Reverb WebSockets (NC-11).
 *
 * Retorna conversas atualizadas após o cursor `since` no formato `last_message_at|conversation_id`.
 */
final class InboxPollController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Conversation::class);

        $tenant = app('tenant');
        $user = $request->user();
        $since = $request->query('since');

        $query = Conversation::with(['channel', 'patient', 'assignedUser'])
            ->where('tenant_id', $tenant->id);

        if ($user->hasRole('medico')) {
            $query->where(function ($q) use ($user): void {
                $q->where('assigned_user_id', $user->id)
                    ->orWhereHas('patient', fn ($pq) => $pq->where('profissional_responsavel_id', $user->id));
            });
        }

        if ($since !== null && $since !== '') {
            $parts = explode('|', (string) $since, 2);

            if (count($parts) === 2) {
                [$sinceDate] = $parts;
                $query->where('updated_at', '>', $sinceDate);
            }
        }

        $conversations = $query->orderByDesc('last_message_at')->limit(50)->get();

        return response()->json([
            'data' => ConversationResource::collection($conversations),
        ]);
    }
}
