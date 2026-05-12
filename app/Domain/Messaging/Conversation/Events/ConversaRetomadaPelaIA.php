<?php

namespace App\Domain\Messaging\Conversation\Events;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T172 — Evento disparado quando a IA retoma o controle de uma conversa,
 * seja por expiração do timer ou por liberação manual do atendente.
 *
 * PAYLOAD CONGELADO — Fase 4 (IA matricial) depende deste schema exato.
 * Qualquer mudança exige amendment da constitution (Princípio III).
 *
 * Modos válidos: 'manual' | 'timeout'
 *
 * @see tests/Unit/Messaging/ConversaIATogglingContractTest.php (gate)
 */
final class ConversaRetomadaPelaIA implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly string $mode,
    ) {}

    public function auditAction(): string
    {
        return 'conversa.retomada_pela_ia';
    }

    public function auditableModel(): ?Model
    {
        return $this->conversation;
    }

    /**
     * Payload CONGELADO — Fase 4 depende das chaves exatas.
     *
     * @return array{conversation_id: int, motivo: string}
     */
    public function auditPayload(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'motivo' => $this->mode,
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
