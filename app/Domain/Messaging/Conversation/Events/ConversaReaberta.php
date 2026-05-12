<?php

namespace App\Domain\Messaging\Conversation\Events;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T106 — Evento disparado quando uma conversa resolvida é reaberta.
 *
 * Auditado via PersistAuditLogListener. Action `conversa.reaberta`.
 * Motivo `nova_msg`: reabertura automática por nova mensagem inbound.
 * Motivo `manual`: reabertura explícita pelo atendente.
 *
 * @see specs/003-omnichannel-inbox/spec.md § NC-2
 */
final class ConversaReaberta implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Conversation $conversation,
        /** @var 'nova_msg'|'manual' */
        public readonly string $motivo = 'manual',
    ) {}

    public function auditAction(): string
    {
        return 'conversa.reaberta';
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
            'conversa_id' => $this->conversation->id,
            'motivo' => $this->motivo,
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
