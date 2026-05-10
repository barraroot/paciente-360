<?php

namespace App\Events\Billing;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento auditável de falha de pagamento (T184 — FR-014).
 * Disparado pelo `StripeWebhookService` em `invoice.payment_failed`.
 */
final class PaymentFailed implements Auditable
{
    use IsAuditable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $stripeInvoiceId,
        public readonly int $failureCount,
    ) {}

    public function auditAction(): string
    {
        return 'subscription.payment_failed';
    }

    public function auditableModel(): ?Model
    {
        return $this->tenant;
    }

    public function auditTenantId(): ?int
    {
        return (int) $this->tenant->id;
    }

    public function auditUserId(): ?int
    {
        return null;
    }

    public function auditActorType(): ?string
    {
        return 'webhook';
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'stripe_invoice_id' => $this->stripeInvoiceId,
            'failure_count' => $this->failureCount,
        ];
    }
}
