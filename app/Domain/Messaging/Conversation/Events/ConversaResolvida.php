<?php

namespace App\Domain\Messaging\Conversation\Events;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T106 — Evento disparado quando uma conversa é resolvida (manual ou auto).
 *
 * Auditado via PersistAuditLogListener. Action `conversa.resolvida`.
 *
 * @see specs/003-omnichannel-inbox/spec.md § NC-2
 */
final class ConversaResolvida implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Conversation $conversation,
        /** @var 'manual'|'auto_inatividade' */
        public readonly string $modo = 'manual',
        public readonly ?int $resolutorId = null,
    ) {}

    public function auditAction(): string
    {
        return 'conversa.resolvida';
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
            'modo' => $this->modo,
            'resolutor_id' => $this->resolutorId,
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
