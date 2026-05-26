<?php

declare(strict_types=1);

namespace App\Domain\Ai\Assignment\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Foundation\Events\Dispatchable;

final class ExecucaoIAFalhou implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $conversationId,
        public readonly ?int $aiPersonaId,
        public readonly string $errorType,
    ) {}

    public function auditAction(): string
    {
        return 'ia.execucao_falhou';
    }

    /** @return array<string, mixed> */
    public function auditPayload(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'ai_persona_id' => $this->aiPersonaId,
            'error_type' => $this->errorType,
        ];
    }

    public function auditTenantId(): ?int
    {
        return $this->tenantId;
    }
}
