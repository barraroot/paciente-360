<?php

declare(strict_types=1);

namespace App\Domain\Ai\Persona\Services;

use App\Domain\Ai\Persona\Models\PersonaTestSession;
use App\Domain\Messaging\Message\Models\Message;
use App\Events\Ai\Persona\PersonaTestMessageBroadcasted;

/**
 * **T183 (Fase 18 — US6, FR-040/042)** — alternativa ao
 * `MessageDispatchService` quando a conversa é parte de uma
 * `PersonaTestSession`.
 *
 * NÃO envia por canal real (Twilio/IG/Evolution). Apenas:
 *  1. Persiste a `Message` com `sandbox=true` + `sandbox_session_id`.
 *  2. Broadcast via Reverb no canal privado `persona-test.{session_id}`.
 *
 * Agregadores existentes (DashboardHomeService, ExecutiveDashboard, métricas)
 * devem filtrar por `WHERE sandbox=false` para NÃO poluir KPIs de produção
 * (T186, FR-042).
 */
final class SandboxOutboundDispatcher
{
    /**
     * Persiste e broadcast a resposta da IA no contexto da sessão sandbox.
     */
    public function dispatchAiResponse(PersonaTestSession $session, string $body, ?string $contentType = 'text'): Message
    {
        $message = Message::create([
            'tenant_id' => $session->tenant_id,
            'conversation_id' => null, // sandbox não tem conversation real
            'direction' => 'out',
            'sender_type' => 'ai',
            'sender_id' => null,
            'body' => $body,
            'body_searchable' => mb_strtolower($body),
            'body_preview' => mb_substr($body, 0, 140),
            'content_type' => $contentType ?? 'text',
            'status' => 'sent',
            'sandbox' => true,
            'sandbox_session_id' => $session->id,
            'external_metadata' => [],
        ]);

        event(new PersonaTestMessageBroadcasted(
            session: $session,
            message: $message,
            kind: 'ia',
        ));

        return $message;
    }

    /**
     * Persiste e broadcast uma mensagem do admin (atuando como paciente).
     */
    public function dispatchAdminMessage(PersonaTestSession $session, string $body, ?string $contentType = 'text'): Message
    {
        $message = Message::create([
            'tenant_id' => $session->tenant_id,
            'conversation_id' => null,
            'direction' => 'in', // admin atuando como paciente
            'sender_type' => 'patient',
            'sender_id' => null,
            'body' => $body,
            'body_searchable' => mb_strtolower($body),
            'body_preview' => mb_substr($body, 0, 140),
            'content_type' => $contentType ?? 'text',
            'status' => 'delivered',
            'sandbox' => true,
            'sandbox_session_id' => $session->id,
            'external_metadata' => [],
        ]);

        event(new PersonaTestMessageBroadcasted(
            session: $session,
            message: $message,
            kind: 'admin',
        ));

        return $message;
    }
}
