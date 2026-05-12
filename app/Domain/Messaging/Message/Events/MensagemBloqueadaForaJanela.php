<?php

namespace App\Domain\Messaging\Message\Events;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Carbon;

/**
 * T108 — Evento auditado quando uma tentativa de envio é bloqueada
 * pela regra de janela 24h (Princípio VI — NÃO-NEGOCIÁVEL).
 *
 * @see specs/003-omnichannel-inbox/spec.md § FR-019 + AC-4.4.5
 */
final class MensagemBloqueadaForaJanela implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly ?Carbon $lastInboundMessageAt,
        public readonly float $hoursSinceLastInbound,
        public readonly ?int $executorId,
    ) {}

    public function auditAction(): string
    {
        return 'mensagem.bloqueada_fora_janela';
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
            'last_inbound_message_at' => $this->lastInboundMessageAt?->toIso8601String(),
            'hours_since_last_inbound' => $this->hoursSinceLastInbound,
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
