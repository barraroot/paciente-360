<?php

namespace App\Http\Controllers\Widget;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Services\ConversationService;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Widget\Models\WebWidgetConfig;
use App\Domain\Messaging\Widget\Services\BusinessHoursEvaluator;
use App\Domain\Messaging\Widget\Services\WidgetAuthService;
use App\Domain\Messaging\Widget\Services\WidgetSessionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Broadcast;

/**
 * T214 — Controller dos endpoints públicos do widget web.
 *
 * Todos públicos (sem auth Sanctum). Protegidos por:
 *  - `throttle:widget-public` (30 req/min/IP)
 *  - `ValidateWidgetOrigin` middleware (exceto bundle)
 *
 * Princípio I — LGPD: visitor_token em header, IP nunca armazenado plain.
 */
class WidgetPublicController extends Controller
{
    public function __construct(
        private readonly WidgetAuthService $widgetAuthService,
        private readonly WidgetSessionService $widgetSessionService,
        private readonly ConversationService $conversationService,
        private readonly BusinessHoursEvaluator $businessHoursEvaluator,
    ) {}

    /**
     * Serve o bundle JS do widget (mesmo arquivo para todos os tenants).
     *
     * Caminho: `public/widget/v1/_bundle/widget.iife.js`
     * Headers: Content-Type JS + Cache-Control 1h + CORS *.
     */
    public function bundle(string $publicKey): Response|JsonResponse
    {
        $bundlePath = public_path('widget/v1/_bundle/widget.iife.js');

        if (! file_exists($bundlePath)) {
            return response()->json(['error' => 'bundle_not_found'], 404);
        }

        $content = file_get_contents($bundlePath);

        return response($content, 200, [
            'Content-Type' => 'application/javascript',
            'Cache-Control' => 'public, max-age=3600',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Retorna configuração pública do widget: aparência, horários, creds Reverb.
     *
     * O widget JS chama este endpoint ao inicializar para personalizar a UI.
     */
    public function config(Request $request, string $publicKey): JsonResponse
    {
        /** @var WebWidgetConfig $config */
        $config = $request->attributes->get('widget_config');

        return response()->json([
            'appearance' => $config->appearance ?? [],
            'initial_message' => $config->initial_message,
            'business_hours' => $config->business_hours ?? [],
            'outside_hours_behavior' => $config->outside_hours_behavior,
            'outside_hours_message' => $config->outside_hours_message,
            'pre_chat_form' => $config->pre_chat_form,
            'reverb' => [
                'app_key' => config('broadcasting.connections.reverb.key', config('reverb.apps.0.key', '')),
                'host' => config('reverb.servers.reverb.host', 'localhost'),
                'port' => config('reverb.servers.reverb.port', 8080),
                'scheme' => config('reverb.servers.reverb.scheme', 'http'),
            ],
        ]);
    }

    /**
     * Inicia ou recupera sessão de visitante (AC-4.3.2, AC-4.3.3).
     */
    public function startSession(Request $request, string $publicKey): JsonResponse
    {
        /** @var WebWidgetConfig $config */
        $config = $request->attributes->get('widget_config');

        $visitorToken = $request->input('visitor_token');
        $preChatData = $request->input('pre_chat_data');

        try {
            $session = $this->widgetSessionService->startSession(
                config: $config,
                visitorToken: $visitorToken,
                preChatData: $preChatData,
                request: $request,
            );

            // 201 for new sessions, 200 for existing
            $isNew = $session->wasRecentlyCreated;
            $status = $isNew ? 201 : 200;

            return response()->json([
                'visitor_token' => $session->visitor_token,
                'session_id' => $session->id,
                'expires_at' => $session->expires_at->toIso8601String(),
            ], $status);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'message' => 'Dados do formulário pré-chat são obrigatórios.',
            ], 422);
        }
    }

    /**
     * Visitante envia mensagem (AC-4.3.4, AC-4.3.7).
     *
     * Header obrigatório: X-Visitor-Token
     * Header recomendado: Idempotency-Key (dedup)
     */
    public function sendMessage(Request $request, string $publicKey): JsonResponse
    {
        /** @var WebWidgetConfig $config */
        $config = $request->attributes->get('widget_config');

        // Validate visitor token header
        $visitorToken = $request->header('X-Visitor-Token');

        if ($visitorToken === null || $visitorToken === '') {
            return response()->json([
                'error' => 'missing_visitor_token',
                'message' => 'O header X-Visitor-Token é obrigatório.',
            ], 422);
        }

        // Validate request body
        $validated = $request->validate([
            'content_type' => ['required', 'string', 'in:text,image,audio,video,document'],
            'body' => ['required_if:content_type,text', 'nullable', 'string', 'max:4096'],
        ]);

        // Find session scoped to this widget
        $session = $this->widgetSessionService->findActiveSessionForConfig(
            (string) $visitorToken,
            $config->id,
        );

        if ($session === null) {
            return response()->json([
                'error' => 'session_not_found',
                'message' => 'Sessão não encontrada ou expirada.',
            ], 404);
        }

        // Validate pre_chat_form for "exigido_para_enviar"
        if ($config->pre_chat_form === 'exigido_para_enviar') {
            $provisional = is_array($session->provisional_data) ? $session->provisional_data : [];
            if (empty($provisional) || empty($provisional['nome'])) {
                return response()->json([
                    'error' => 'pre_chat_required',
                    'message' => 'Dados do formulário pré-chat são obrigatórios para enviar mensagens.',
                ], 422);
            }
        }

        // Evaluate business hours
        $businessHours = is_array($config->business_hours) ? $config->business_hours : [];
        $isWithinHours = $this->businessHoursEvaluator->isWithinBusinessHours($businessHours);
        $behavior = $config->outside_hours_behavior;

        if (! $isWithinHours && $behavior === 'bloqueia') {
            return response()->json([
                'error' => 'outside_business_hours',
                'message' => $config->outside_hours_message ?? 'Estamos fechados no momento.',
            ], 403);
        }

        // Find or create channel
        $channel = $config->channel;

        if ($channel === null) {
            return response()->json(['error' => 'channel_not_found'], 404);
        }

        // Idempotency check — use withoutGlobalScope since no tenant in container for public endpoint
        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existingMessage = Message::withoutGlobalScopes()->where('idempotency_key', $idempotencyKey)->first();
            if ($existingMessage !== null) {
                return response()->json([
                    'message_id' => $existingMessage->id,
                    'conversation_id' => $existingMessage->conversation_id,
                    'status' => 'deduplicated',
                ], 201);
            }
        }

        // Find or create conversation
        $conversation = $this->conversationService->findOrCreateForInbound(
            channel: $channel,
            externalThreadId: $session->visitor_token,
        );

        // Mark outside hours if in fila mode
        if (! $isWithinHours && $behavior === 'fila') {
            $conversation->update(['received_outside_hours' => true]);
        }

        // Create message — visitor maps to 'patient' sender_type (anonymous until identified)
        $message = Message::create([
            'tenant_id' => $config->tenant_id,
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'sender_type' => 'patient', // anonymous visitor (patient_id null until identified)
            'content_type' => $validated['content_type'],
            'body' => $validated['body'] ?? null,
            'body_preview' => isset($validated['body']) ? mb_substr((string) $validated['body'], 0, 140) : null,
            'body_searchable' => $validated['body'] ?? null,
            'status' => 'delivered',
            'external_id' => $idempotencyKey,
            'idempotency_key' => $idempotencyKey,
            'external_metadata' => [],
        ]);

        // Update conversation unread count
        $conversation->increment('unread_count');

        // Broadcast to tenant inbox
        try {
            Broadcast::on("tenant.{$config->tenant_id}.inbox")
                ->as('conversation.updated')
                ->with([
                    'conversation_id' => $conversation->id,
                    'channel_id' => $channel->id,
                    'channel_type' => 'web',
                    'unread_count' => $conversation->fresh()?->unread_count ?? 0,
                ])->send();
        } catch (\Exception) {
            // Non-fatal: inbox update via broadcast is best-effort
        }

        // Update session last_activity
        $session->update(['last_activity_at' => now()]);

        return response()->json([
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
            'status' => 'received',
        ], 201);
    }

    /**
     * SSE stream de mensagens (fallback para quando Reverb WebSocket falha).
     *
     * Stub minimalista — emite heartbeat a cada 30s.
     * Implementação completa na Fase 4 se necessário.
     */
    public function messagesStream(Request $request, string $publicKey): Response
    {
        /** @var WebWidgetConfig $config */
        $config = $request->attributes->get('widget_config');

        return response(function () use ($config): void {
            echo "data: {\"type\":\"connected\",\"tenant_id\":{$config->tenant_id}}\n\n";
            ob_flush();
            flush();

            // Keep connection alive with periodic heartbeat (30s)
            $end = time() + 30;
            while (time() < $end && ! connection_aborted()) {
                echo "data: {\"type\":\"heartbeat\"}\n\n";
                ob_flush();
                flush();
                sleep(10);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
