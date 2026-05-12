<?php

namespace App\Http\Controllers\Api\V1\Inbox;

use App\Domain\Messaging\Conversation\Contracts\ConversaIATogglingContract;
use App\Domain\Messaging\Conversation\Events\ConversaAssumidaPorHumano;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inbox\TakeoverRequest;
use App\Http\Resources\V1\ConversationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * T176 — Controller para Modo Humano Assume (US-4.6).
 *
 * Pipeline: TakeoverRequest → Controller → HumanTakeoverService → ConversationResource.
 *
 * Rotas:
 *  - POST /inbox/conversations/{conversation}/takeover      → takeover()
 *  - POST /inbox/conversations/{conversation}/release-to-ai → releaseToAi()
 */
final class TakeoverController extends Controller
{
    /**
     * POST /inbox/conversations/{conversation}/takeover
     *
     * Pausa a IA na conversa pelo tempo especificado (ou padrão do tenant).
     * Aceita:
     *  - {} (padrão do config/tenant — clamped 5-240 min)
     *  - {"duration_hours": 4} (clamped 5-240 min)
     *  - {"until": "2026-05-12T18:00:00Z"} (timestamp explícito, sem clamp)
     */
    public function takeover(
        TakeoverRequest $request,
        ConversaIATogglingContract $service,
        Conversation $conversation,
    ): JsonResponse {
        $alreadyPaused = $service->isPaused($conversation);
        $reason = $alreadyPaused ? 'reprise' : 'manual_click';

        // When `until` timestamp is provided explicitly, bypass the minute-based
        // service method to avoid clamping the user's explicit choice.
        if ($request->has('until') && $request->input('until') !== null) {
            $until = Carbon::parse($request->input('until'));
            $durationSeconds = (int) now()->diffInSeconds($until, false);

            $conversation->update([
                'ai_paused_until' => $until,
                'ai_pause_set_by' => $request->user()?->id,
            ]);
            $conversation->refresh();

            event(new ConversaAssumidaPorHumano(
                conversation: $conversation,
                executorId: $request->user()?->id,
                reason: $reason,
                pauseDurationSeconds: $durationSeconds,
            ));

            return (new ConversationResource($conversation))->response()->setStatusCode(200);
        }

        $minutes = $this->resolveMinutes($request, $conversation);

        $conversation = $service->pauseAi(
            conv: $conversation,
            minutes: $minutes,
            byUser: $request->user(),
            reason: $reason,
        );

        return (new ConversationResource($conversation))->response()->setStatusCode(200);
    }

    /**
     * POST /inbox/conversations/{conversation}/release-to-ai
     *
     * Libera a IA imediatamente. Idempotente.
     */
    public function releaseToAi(
        TakeoverRequest $request,
        ConversaIATogglingContract $service,
        Conversation $conversation,
    ): JsonResponse {
        $conversation = $service->resumeAi($conversation, 'manual');

        return (new ConversationResource($conversation))->response()->setStatusCode(200);
    }

    /**
     * Resolve a duração em minutos (para duration_hours ou default).
     *
     * Precedência: duration_hours * 60 > tenant/config default.
     * Note: 'until' case is handled separately in takeover() to avoid clamping.
     */
    private function resolveMinutes(TakeoverRequest $request, Conversation $conversation): int
    {
        if ($request->has('duration_hours') && $request->input('duration_hours') !== null) {
            return (int) $request->input('duration_hours') * 60;
        }

        return (int) config('messaging.ai_pause.minutes', 30);
    }
}
