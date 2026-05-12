<?php

namespace App\Http\Controllers\Api\V1\Inbox;

use App\Domain\Messaging\Presence\Services\PresenceTrackerService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inbox\UpdatePresenceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * T263 — Controller de presença de atendentes (NC-6.b).
 *
 * Pipeline: FormRequest → Controller (fino) → PresenceTrackerService.
 */
final class PresenceController extends Controller
{
    /**
     * POST /api/v1/inbox/presence/heartbeat
     *
     * Registra heartbeat — atualiza `last_seen_at` para agora.
     * Cria linha se não existir. Retorna 204 No Content.
     */
    public function heartbeat(Request $request, PresenceTrackerService $service): Response
    {
        $service->heartbeat($request->user());

        return response()->noContent();
    }

    /**
     * GET /api/v1/inbox/presence
     *
     * Lista atendentes do tenant com status inferido (online/offline).
     */
    public function index(Request $request, PresenceTrackerService $service): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;

        return response()->json([
            'data' => $service->listForTenant($tenantId),
        ]);
    }

    /**
     * PATCH /api/v1/inbox/presence/me
     *
     * Atualiza preferências do atendente atual.
     */
    public function updateMe(UpdatePresenceRequest $request, PresenceTrackerService $service): JsonResponse
    {
        $user = $request->user();
        $presence = $service->heartbeat($user);

        $data = [];

        if ($request->has('max_concurrent_conversations')) {
            $data['max_concurrent_conversations'] = $request->integer('max_concurrent_conversations');
        }

        if ($request->has('notification_preferences')) {
            $data['notification_preferences'] = $request->input('notification_preferences');
        }

        if ($data !== []) {
            $presence->update($data);
        }

        return response()->json($presence->fresh());
    }
}
