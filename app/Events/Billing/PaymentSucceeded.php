<?php

namespace App\Events\Billing;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento auditável de pagamento bem-sucedido (T184 — FR-014).
 * Disparado pelo `StripeWebhookService` em `invoice.payment_succeeded`.
 */
final class PaymentSucceeded implements Auditable
{
    use IsAuditable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $stripeInvoiceId,
        public readonly bool $wasOverdue,
    ) {}

    public function auditAction(): string
    {
        return 'subscription.payment_succeeded';
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
            'recovered_from_overdue' => $this->wasOverdue,
        ];
    }
}
