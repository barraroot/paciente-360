<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * **T222 (Fase 8 — Lote D US-11.2)** — Token API público revogado.
 */
final class TokenApiRevogado implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $tokenId,
        public readonly string $tokenName,
        public readonly int $revokedByUserId,
        public readonly string $reason = 'manual',
    ) {}

    public function auditAction(): string
    {
        return 'api_token.revoked';
    }

    public function auditPayload(): array
    {
        return [
            'token_id' => $this->tokenId,
            'token_name' => $this->tokenName,
            'reason' => $this->reason,
        ];
    }

    public function auditableModel(): ?Model
    {
        return null;
    }

    public function auditTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function auditUserId(): ?int
    {
        return $this->revokedByUserId;
    }

    public function auditActorType(): ?string
    {
        return 'user';
    }
}
