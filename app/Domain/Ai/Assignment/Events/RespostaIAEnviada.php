<?php

declare(strict_types=1);

namespace App\Domain\Ai\Assignment\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Foundation\Events\Dispatchable;

final class RespostaIAEnviada implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $conversationId,
        public readonly int $aiPersonaId,
        public readonly ?int $messageId,
        public readonly string $intent,
    ) {}

    public function auditAction(): string
    {
        return 'ia.resposta_enviada';
    }

    /** @return array<string, mixed> */
    public function auditPayload(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'ai_persona_id' => $this->aiPersonaId,
            'message_id' => $this->messageId,
            'intent' => $this->intent,
        ];
    }

    public function auditTenantId(): ?int
    {
        return $this->tenantId;
    }
}
