<?php

namespace App\Events\Billing;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento auditável de assinatura atualizada (T202 — FR-016).
 *
 * Disparado após upgrade ou downgrade de plano ou quantidade de profissionais.
 * Payload inclui estado anterior e novo, proration_behavior e se foi upgrade.
 */
final class SubscriptionUpdated implements Auditable
{
    use IsAuditable;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly Tenant $tenant,
        public readonly array $payload,
    ) {}

    public function auditAction(): string
    {
        return 'subscription.updated';
    }

    public function auditableModel(): ?Model
    {
        return $this->tenant;
    }

    public function auditTenantId(): ?int
    {
        return (int) $this->tenant->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return $this->payload;
    }
}
