<?php

namespace App\Domain\Messaging\Assignment\Events;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T149 — Evento disparado quando uma conversa é atribuída a um usuário.
 *
 * Auditado via PersistAuditLogListener. Action `conversa.atribuida`.
 *
 * @see specs/003-omnichannel-inbox/spec.md § AC-4.5.1
 */
final class ConversaAtribuida implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Conversation $conversation,
        /** @var 'manual'|'auto_round_robin'|'auto_patient_owner'|'transfer' */
        public readonly string $mode,
        public readonly ?int $executorId = null,
    ) {}

    public function auditAction(): string
    {
        return 'conversa.atribuida';
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
            'user_id' => $this->conversation->assigned_user_id,
            'mode' => $this->mode,
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
