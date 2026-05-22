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
 * **T222 (Fase 8 — Lote D US-11.2)** — Token API público emitido.
 */
final class TokenApiEmitido implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    /**
     * @param array<int, string> $abilities
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $tokenId,
        public readonly string $tokenName,
        public readonly array $abilities,
        public readonly int $createdByUserId,
    ) {}

    public function auditAction(): string
    {
        return 'api_token.emitted';
    }

    public function auditPayload(): array
    {
        return [
            'token_id' => $this->tokenId,
            'token_name' => $this->tokenName,
            'abilities' => $this->abilities,
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
        return $this->createdByUserId;
    }

    public function auditActorType(): ?string
    {
        return 'user';
    }
}
