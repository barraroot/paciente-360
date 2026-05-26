<?php

declare(strict_types=1);

namespace App\Domain\Ai\Assignment\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Foundation\Events\Dispatchable;

final class IAEscalouParaHumano implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $conversationId,
        public readonly ?int $aiPersonaId,
        public readonly string $reason,
    ) {}

    public function auditAction(): string
    {
        return 'ia.escalou_humano';
    }

    /** @return array<string, mixed> */
    public function auditPayload(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'ai_persona_id' => $this->aiPersonaId,
            'reason' => $this->reason,
        ];
    }

    public function auditTenantId(): ?int
    {
        return $this->tenantId;
    }
}
