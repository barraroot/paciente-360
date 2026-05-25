<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Notification\Services;

use App\Domain\Messaging\Channel\Adapters\OutboundMessage;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Message\Services\MessageDispatchService;
use App\Domain\Messaging\Notification\DataTransfer\NotificationRequest;
use App\Domain\Messaging\Notification\DataTransfer\ResolvedChannel;
use App\Domain\Messaging\Notification\Enums\NotificationSkipReason;
use App\Domain\Messaging\Notification\Enums\NotificationStatus;
use App\Domain\Messaging\Notification\Events\NotificacaoEnviada;
use App\Domain\Messaging\Notification\Events\NotificacaoRoteadaParaManual;
use App\Domain\Messaging\Notification\Events\NotificacaoSuprimida;
use App\Domain\Messaging\Notification\Models\NotificationTemplate;
use App\Domain\Messaging\Notification\Models\OutboundNotification;
use App\Domain\Prescription\Preferences\PatientProfessionalPreference;
use App\Support\Metrics\OutboundNotificationMetricsContract;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Feature 013 — Orquestrador central de entrega de notificações outbound.
 *
 * Único ponto que chama {@see MessageDispatchService::send}. Curto-circuita na
 * ordem determinística R5 (opt_out → debounce → idempotência → canal →
 * janela/template → envio). NUNCA lança ao listener: toda falha vira um
 * `OutboundNotification` em estado terminal (`pending_manual`/`skipped`).
 *
 * @see specs/013-outbound-notifications/research.md §R5, §R8, §R10
 * @see specs/013-outbound-notifications/contracts/outbound-notifications.md §1
 */
final class OutboundNotificationDispatcher
{
    private const DEBOUNCE_TTL_SECONDS = 14400; // 4h (FR-013)

    public function __construct(
        private readonly OutboundChannelResolver $channels,
        private readonly NotificationTemplateResolver $templates,
        private readonly MessageDispatchService $messages,
        private readonly OutboundNotificationMetricsContract $metrics,
    ) {}

    public function dispatch(NotificationRequest $request): OutboundNotification
    {
        // --- 1. Opt-out (apenas tipos sujeitos ao opt-out de renovação) ---
        if ($this->isOptedOut($request)) {
            return $this->terminalSkipped($request, NotificationSkipReason::OptOut);
        }

        // --- 2. Debounce 4h por (paciente, tipo) ---
        if ($this->isDebounced($request)) {
            return $this->terminalSkipped($request, NotificationSkipReason::Debounced);
        }

        // --- 3. Idempotência: mesmo (paciente, tipo, marco, data) não duplica ---
        $existing = $this->findExistingForToday($request);
        if ($existing !== null) {
            return $existing;
        }

        $notification = $this->createQueued($request);

        // --- 4. Resolver canal/conversa ---
        $resolved = $this->channels->resolve($request->tenantId, $request->patientId);

        if ($resolved === null) {
            return $this->routeToManual($notification, null, NotificationSkipReason::NoChannel);
        }

        $notification->update([
            'channel_id' => $resolved->channel->id,
            'conversation_id' => $resolved->conversation->id,
        ]);

        // --- 5. Janela 24h + template ---
        $outbound = $this->buildOutboundMessage($request, $resolved, $notification);

        if ($outbound === null) {
            // Sem template aprovado fora da janela (Princípio VI) → contato manual.
            return $this->routeToManual($notification, $resolved->conversation, NotificationSkipReason::NoTemplate);
        }

        // --- 6. Enviar ---
        try {
            $message = $this->messages->send($resolved->conversation, $outbound);

            $notification->update([
                'message_id' => $message->id,
                'status' => NotificationStatus::Sent,
                'sent_at' => now(),
            ]);

            event(new NotificacaoEnviada($notification));
            $this->metrics->recorded($request->tenantId, $request->type->value, NotificationStatus::Sent->value);

            return $notification;
        } catch (\Throwable $e) {
            Log::warning('outbound_notification.send_failed', [
                'notification_id' => $notification->id,
                'tenant_id' => $request->tenantId,
                'error' => $e->getMessage(),
            ]);

            return $this->routeToManual($notification, $resolved->conversation, NotificationSkipReason::SendFailed);
        }
    }

    /**
     * Reconciliação (R7): provedor confirmou entrega → sent → delivered.
     */
    public function reconcileDelivered(OutboundNotification $notification): void
    {
        if ($notification->status !== NotificationStatus::Sent) {
            return;
        }

        $notification->update([
            'status' => NotificationStatus::Delivered,
            'delivered_at' => now(),
        ]);

        if ($notification->sent_at !== null) {
            $this->metrics->deliveryLatency((float) $notification->sent_at->diffInSeconds(now()));
        }

        $this->metrics->recorded(
            $notification->tenant_id,
            $notification->notification_type->value,
            NotificationStatus::Delivered->value,
        );
    }

    /**
     * Reconciliação (R7): falha definitiva do provedor → failed → pending_manual.
     */
    public function reconcileFailed(OutboundNotification $notification): void
    {
        if ($notification->status->isTerminal() || $notification->status === NotificationStatus::Failed) {
            return;
        }

        $notification->update([
            'status' => NotificationStatus::Failed,
            'failed_at' => now(),
        ]);

        $this->routeToManual($notification, $notification->conversation, NotificationSkipReason::SendFailed);
    }

    private function isOptedOut(NotificationRequest $request): bool
    {
        if (! $request->type->respectsRenewalOptOut() || $request->professionalId === null) {
            return false;
        }

        $pref = PatientProfessionalPreference::withoutTenantScope()
            ->where('patient_id', $request->patientId)
            ->where('professional_id', $request->professionalId)
            ->first();

        return $pref?->suppress_renewal_notifications === true;
    }

    private function isDebounced(NotificationRequest $request): bool
    {
        $key = "messaging_debounce:notification:{$request->type->value}:{$request->patientId}";

        return Redis::set($key, 1, 'EX', self::DEBOUNCE_TTL_SECONDS, 'NX') === false;
    }

    private function findExistingForToday(NotificationRequest $request): ?OutboundNotification
    {
        return OutboundNotification::withoutTenantScope()
            ->where('tenant_id', $request->tenantId)
            ->where('patient_id', $request->patientId)
            ->where('notification_type', $request->type->value)
            ->where('milestone', $request->milestone)
            ->where('status', '<>', NotificationStatus::Skipped->value)
            ->whereDate('created_at', now()->toDateString())
            ->first();
    }

    private function createQueued(NotificationRequest $request): OutboundNotification
    {
        try {
            return OutboundNotification::create([
                'tenant_id' => $request->tenantId,
                'patient_id' => $request->patientId,
                'notification_type' => $request->type,
                'milestone' => $request->milestone,
                'status' => NotificationStatus::Queued,
                'source_type' => $request->sourceType,
                'source_id' => $request->sourceId,
            ]);
        } catch (QueryException $e) {
            // Race no índice UNIQUE de idempotência → devolve o registro existente.
            $existing = $this->findExistingForToday($request);

            if ($existing !== null) {
                return $existing;
            }

            throw $e;
        }
    }

    /**
     * Cria/atualiza um registro terminal `skipped` e emite o evento auditável.
     */
    private function terminalSkipped(NotificationRequest $request, NotificationSkipReason $reason): OutboundNotification
    {
        $notification = OutboundNotification::create([
            'tenant_id' => $request->tenantId,
            'patient_id' => $request->patientId,
            'notification_type' => $request->type,
            'milestone' => $request->milestone,
            'status' => NotificationStatus::Skipped,
            'skip_reason' => $reason,
            'source_type' => $request->sourceType,
            'source_id' => $request->sourceId,
        ]);

        event(new NotificacaoSuprimida($notification));
        $this->metrics->skipped($reason->value);
        $this->metrics->recorded($request->tenantId, $request->type->value, NotificationStatus::Skipped->value);

        return $notification;
    }

    /**
     * Monta a `OutboundMessage`: texto livre dentro da janela ou template fora.
     * Retorna null quando exige template e não há template aprovado.
     */
    private function buildOutboundMessage(
        NotificationRequest $request,
        ResolvedChannel $resolved,
        OutboundNotification $notification,
    ): ?OutboundMessage {
        $idempotencyKey = $this->idempotencyKey($request);

        if ($resolved->withinWindow && $request->freeFormBody !== null && $request->freeFormBody !== '') {
            return new OutboundMessage(
                conversationExternalThreadId: $resolved->conversation->external_thread_id,
                contentType: 'text',
                body: $request->freeFormBody,
                idempotencyKey: $idempotencyKey,
            );
        }

        $template = $this->templates->resolve($request->tenantId, $request->type, $resolved->channel);

        if ($template === null) {
            return null;
        }

        $notification->update(['notification_template_id' => $template->id]);

        return new OutboundMessage(
            conversationExternalThreadId: $resolved->conversation->external_thread_id,
            contentType: 'template',
            templateProviderId: $template->provider_template_id,
            templateVariables: $this->buildTemplateVariables($template, $request->context),
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * Mapeia variáveis do template a partir do contexto NÃO-CLÍNICO, restrito
     * à allow-list (defesa em profundidade LGPD — R9).
     *
     * @param array<string, scalar|null> $context
     * @return array<string, scalar|null>
     */
    private function buildTemplateVariables(NotificationTemplate $template, array $context): array
    {
        $variables = [];

        foreach ($template->variables_map as $templateVar => $contextKey) {
            if (! in_array($templateVar, NotificationTemplateResolver::ALLOWED_VARIABLES, true)) {
                continue;
            }

            $source = is_string($contextKey) ? $contextKey : $templateVar;
            $variables[$templateVar] = $context[$source] ?? ($context[$templateVar] ?? null);
        }

        return $variables;
    }

    private function idempotencyKey(NotificationRequest $request): string
    {
        return sprintf(
            'notif:%d:%s:%d:%s:%s',
            $request->tenantId,
            $request->type->value,
            $request->patientId,
            $request->milestone,
            now()->toDateString(),
        );
    }

    /**
     * Materializa a pendência de contato manual: mensagem de sistema na conversa
     * do paciente + conversa sinalizada (priority alta) — R10/FR-018.
     */
    private function routeToManual(
        OutboundNotification $notification,
        ?Conversation $conversation,
        NotificationSkipReason $reason,
    ): OutboundNotification {
        $notification->update([
            'status' => NotificationStatus::PendingManual,
            'skip_reason' => $reason,
            'failed_at' => $reason === NotificationSkipReason::SendFailed ? now() : $notification->failed_at,
        ]);

        $conversation ??= $this->findExistingConversation($notification);

        if ($conversation !== null) {
            $this->postSystemMessage($conversation, $notification, $reason);

            if ($conversation->priority !== 'alta') {
                $conversation->update(['priority' => 'alta']);
            }
        }

        event(new NotificacaoRoteadaParaManual($notification));
        $this->metrics->pendingManual($notification->tenant_id, $reason->value);
        $this->metrics->recorded($notification->tenant_id, $notification->notification_type->value, NotificationStatus::PendingManual->value);

        return $notification;
    }

    private function findExistingConversation(OutboundNotification $notification): ?Conversation
    {
        return Conversation::withoutGlobalScopes()
            ->where('tenant_id', $notification->tenant_id)
            ->where('patient_id', $notification->patient_id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->first();
    }

    private function postSystemMessage(
        Conversation $conversation,
        OutboundNotification $notification,
        NotificationSkipReason $reason,
    ): void {
        $body = sprintf(
            'Notificação não entregue automaticamente — contatar paciente manualmente. '
            .'Motivo: %s. Tipo: %s. Marco: %s.',
            $reason->value,
            $notification->notification_type->value,
            $notification->milestone,
        );

        Message::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'sender_type' => 'system',
            'body' => $body,
            'body_searchable' => mb_strtolower($body),
            'body_preview' => mb_substr($body, 0, 140),
            'content_type' => 'text',
            'status' => 'sent',
            'external_metadata' => ['outbound_notification_id' => $notification->id],
        ]);
    }
}
