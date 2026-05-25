<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Notification\Services;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Services\ConversationService;
use App\Domain\Messaging\Notification\DataTransfer\ResolvedChannel;
use App\Models\Paciente;

/**
 * Feature 013 — Resolve o canal/conversa de saída para um paciente (R1/R2).
 *
 * Envio proativo é WhatsApp-only (único canal com template HSM fora da janela —
 * Princípio VI / clarificação Q2). O thread id é o `telefone_primario_normalizado`
 * do paciente. Sem WhatsApp ativo ou sem telefone → null (→ pending_manual/no_channel).
 *
 * @see specs/013-outbound-notifications/research.md §R1
 */
final class OutboundChannelResolver
{
    public function __construct(
        private readonly ConversationService $conversations,
    ) {}

    public function resolve(int $tenantId, int $patientId): ?ResolvedChannel
    {
        /** @var Channel|null $channel */
        $channel = Channel::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('type', 'whatsapp')
            ->where('status', 'ativo')
            ->orderBy('id')
            ->first();

        if ($channel === null) {
            return null;
        }

        /** @var Paciente|null $patient */
        $patient = Paciente::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($patientId)
            ->first();

        $threadId = $patient?->telefone_primario_normalizado;

        if ($patient === null || $threadId === null || $threadId === '') {
            return null;
        }

        $conversation = $this->conversations->findOrCreateForPatientChannel(
            $channel,
            $threadId,
            $patientId,
        );

        $withinWindow = $conversation->last_inbound_message_at !== null
            && $conversation->last_inbound_message_at->diffInHours(now()) < 24;

        return new ResolvedChannel($channel, $conversation, $withinWindow);
    }
}
