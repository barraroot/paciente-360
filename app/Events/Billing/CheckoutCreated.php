<?php

namespace App\Events\Billing;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento auditável de sessão de checkout criada (T185).
 */
final class CheckoutCreated implements Auditable
{
    use IsAuditable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $planCode,
        public readonly int $professionalsQuantity,
    ) {}

    public function auditAction(): string
    {
        return 'subscription.checkout.created';
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
        return [
            'plan_code' => $this->planCode,
            'professionals_quantity' => $this->professionalsQuantity,
        ];
    }
}
