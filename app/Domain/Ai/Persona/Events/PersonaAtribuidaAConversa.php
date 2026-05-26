<?php

declare(strict_types=1);

namespace App\Domain\Ai\Persona\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Foundation\Events\Dispatchable;

final class PersonaAtribuidaAConversa implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $conversationId,
        public readonly int $aiPersonaId,
        public readonly string $channelType,
    ) {}

    public function auditAction(): string
    {
        return 'ia.persona_atribuida';
    }

    /** @return array<string, mixed> */
    public function auditPayload(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'ai_persona_id' => $this->aiPersonaId,
            'channel_type' => $this->channelType,
        ];
    }

    public function auditTenantId(): ?int
    {
        return $this->tenantId;
    }
}
