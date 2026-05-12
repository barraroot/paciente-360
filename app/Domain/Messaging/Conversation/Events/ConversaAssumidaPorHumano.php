<?php

namespace App\Domain\Messaging\Conversation\Events;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T172 — Evento disparado quando um humano assume o controle de uma conversa,
 * pausando a IA explicitamente ou implicitamente.
 *
 * PAYLOAD CONGELADO — Fase 4 (IA matricial) depende deste schema exato.
 * Qualquer mudança exige amendment da constitution (Princípio III).
 *
 * Motivos válidos: 'manual_click' | 'mensagem_enviada' | 'reprise'
 *
 * @see tests/Unit/Messaging/ConversaIATogglingContractTest.php (gate)
 */
final class ConversaAssumidaPorHumano implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly ?int $executorId,
        public readonly string $reason,
        public readonly int $pauseDurationSeconds,
    ) {}

    public function auditAction(): string
    {
        return 'conversa.assumida_por_humano';
    }

    public function auditableModel(): ?Model
    {
        return $this->conversation;
    }

    /**
     * Payload CONGELADO — Fase 4 depende das chaves exatas.
     *
     * @return array{conversation_id: int, executor_id: int|null, motivo: string, duracao_pausa_seg: int}
     */
    public function auditPayload(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'executor_id' => $this->executorId,
            'motivo' => $this->reason,
            'duracao_pausa_seg' => $this->pauseDurationSeconds,
        ];
    }

    public function auditTenantId(): ?int
    {
        return $this->conversation->tenant_id;
    }

    public function auditUserId(): ?int
    {
        return $this->executorId;
    }

    public function relatedPacienteId(): ?int
    {
        return $this->conversation->patient_id;
    }
}
