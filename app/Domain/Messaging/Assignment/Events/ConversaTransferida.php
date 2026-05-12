<?php

namespace App\Domain\Messaging\Assignment\Events;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T149 — Evento disparado quando uma conversa é transferida para outro usuário ou role.
 *
 * Auditado via PersistAuditLogListener. Action `conversa.transferida`.
 * A nota interna é incluída no payload de auditoria (LGPD minimização aplicada pelo caller).
 *
 * @see specs/003-omnichannel-inbox/spec.md § AC-4.5.3
 */
final class ConversaTransferida implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly ?int $fromUserId,
        public readonly ?int $toUserId,
        public readonly ?string $toRole,
        public readonly string $noteSanitized,
        public readonly int $executorId,
    ) {}

    public function auditAction(): string
    {
        return 'conversa.transferida';
    }

    public function auditableModel(): ?Model
    {
        return $this->conversation;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'from_user_id' => $this->fromUserId,
            'to_user_id' => $this->toUserId,
            'to_role' => $this->toRole,
            'note_sanitized' => $this->noteSanitized,
            'executor_id' => $this->executorId,
        ];
    }

    public function auditTenantId(): ?int
    {
        return $this->conversation->tenant_id;
    }

    public function relatedPacienteId(): ?int
    {
        return $this->conversation->patient_id;
    }
}
