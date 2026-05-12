<?php

namespace App\Domain\Messaging\Conversation\Events;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T083 — Evento disparado quando uma conversa é vinculada a um paciente.
 *
 * Action `conversa.vinculada_paciente`. Auditado via PersistAuditLogListener.
 */
final class ConversaVinculadaAPaciente implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly int $pacienteId,
        public readonly string $modo = 'auto_telefone',
    ) {}

    public function auditAction(): string
    {
        return 'conversa.vinculada_paciente';
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
            'paciente_id' => $this->pacienteId,
            'modo' => $this->modo,
        ];
    }

    public function auditTenantId(): ?int
    {
        return $this->conversation->tenant_id;
    }

    public function relatedPacienteId(): ?int
    {
        return $this->pacienteId;
    }
}
