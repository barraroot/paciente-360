<?php

namespace App\Services\Billing;

use App\Events\Billing\PaymentFailed;
use App\Events\Billing\PaymentSucceeded;
use App\Events\Billing\TenantRecovered;
use App\Jobs\Email\SendPaymentFailedEmailJob;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de processamento de webhooks Stripe (T184).
 *
 * Responsável por interpretar os eventos Stripe e atualizar o estado
 * do tenant de acordo com as regras de negócio. Auditável via eventos.
 *
 * LGPD: payload de webhook NÃO loga dados de cartão; apenas metadata
 * estrutural (invoice_id, failure_count, etc.).
 */
class StripeWebhookService
{
    /**
     * Despacha o evento para o handler correto.
     *
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload): void
    {
        $type = $payload['type'] ?? '';

        match ($type) {
            'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($payload),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($payload),
            'customer.subscription.created' => $this->handleCustomerSubscriptionCreated($payload),
            'customer.subscription.updated' => $this->handleCustomerSubscriptionUpdated($payload),
            'customer.subscription.deleted' => $this->handleCustomerSubscriptionDeleted($payload),
            'charge.dispute.created' => $this->handleChargeDisputeCreated($payload),
            default => null,
        };
    }

    /**
     * Pagamento bem-sucedido: restaura tenant `overdue` para `active`.
     *
     * @param array<string, mixed> $payload
     */
    public function handleInvoicePaymentSucceeded(array $payload): void
    {
        $customerId = $payload['data']['object']['customer'] ?? null;

        if (! $customerId) {
            return;
        }

        $tenant = $this->findTenantByStripeCustomerId($customerId);

        if (! $tenant) {
            return;
        }

        $wasOverdue = $tenant->status === 'overdue';
        $stripeInvoiceId = $payload['data']['object']['id'] ?? '';

        if ($wasOverdue) {
            $tenant->status = 'active';
            $tenant->overdue_since = null;
            $tenant->restrictions_applied_at = null;
            $tenant->save();

            Event::dispatch(new TenantRecovered($tenant));
        }

        Event::dispatch(new PaymentSucceeded($tenant, $stripeInvoiceId, $wasOverdue));
    }

    /**
     * Falha de pagamento: conta falhas e marca `overdue` após 3 consecutivas.
     *
     * @param array<string, mixed> $payload
     */
    public function handleInvoicePaymentFailed(array $payload): void
    {
        $customerId = $payload['data']['object']['customer'] ?? null;

        if (! $customerId) {
            return;
        }

        $tenant = $this->findTenantByStripeCustomerId($customerId);

        if (! $tenant) {
            return;
        }

        $stripeInvoiceId = $payload['data']['object']['id'] ?? '';

        // Conta falhas únicas por invoice_id (DISTINCT) para evitar dupla contagem.
        // O listener Auditable pode ser registrado múltiplas vezes (auto-discovery
        // + registro manual), gerando entradas duplicadas por evento.
        $failureCount = AuditLog::query()
            ->where('tenant_id', $tenant->id)
            ->where('action', 'subscription.payment_failed')
            ->whereRaw("payload->>'stripe_invoice_id' IS NOT NULL")
            ->selectRaw("COUNT(DISTINCT payload->>'stripe_invoice_id') as cnt")
            ->value('cnt') + 1;

        Event::dispatch(new PaymentFailed($tenant, $stripeInvoiceId, $failureCount));

        if ($failureCount >= (int) config('billing.payment_retries', 3) && $tenant->status !== 'overdue') {
            $tenant->status = 'overdue';
            $tenant->overdue_since = now();
            $tenant->save();
        }

        // Notifica admin-clinica e financeiro sobre a falha.
        $tenant->users()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin-clinica', 'financeiro']))
            ->each(fn ($user) => SendPaymentFailedEmailJob::dispatch($tenant->id, $user->id));
    }

    /**
     * Assinatura criada: sincroniza snapshot do plano.
     *
     * @param array<string, mixed> $payload
     */
    public function handleCustomerSubscriptionCreated(array $payload): void
    {
        $customerId = $payload['data']['object']['customer'] ?? null;

        if (! $customerId) {
            return;
        }

        $tenant = $this->findTenantByStripeCustomerId($customerId);

        if (! $tenant) {
            return;
        }

        $this->syncSubscriptionSnapshot($tenant, $payload['data']['object']);
    }

    /**
     * Assinatura atualizada: re-sincroniza snapshot do plano.
     *
     * @param array<string, mixed> $payload
     */
    public function handleCustomerSubscriptionUpdated(array $payload): void
    {
        $this->handleCustomerSubscriptionCreated($payload);
    }

    /**
     * Assinatura cancelada: marca tenant como cancelled.
     *
     * @param array<string, mixed> $payload
     */
    public function handleCustomerSubscriptionDeleted(array $payload): void
    {
        $customerId = $payload['data']['object']['customer'] ?? null;

        if (! $customerId) {
            return;
        }

        $tenant = $this->findTenantByStripeCustomerId($customerId);

        if (! $tenant) {
            return;
        }

        $tenant->status = 'cancelled';
        $tenant->save();

        $stripeSubId = $payload['data']['object']['id'] ?? null;

        if ($stripeSubId) {
            DB::table('subscriptions')
                ->where('tenant_id', $tenant->id)
                ->where('stripe_id', $stripeSubId)
                ->update(['ends_at' => now(), 'stripe_status' => 'canceled']);
        }
    }

    /**
     * Disputa criada: apenas auditoria — humano decide.
     *
     * @param array<string, mixed> $payload
     */
    public function handleChargeDisputeCreated(array $payload): void
    {
        Log::channel('stack')->warning('stripe.dispute.created', [
            'dispute_id' => $payload['data']['object']['id'] ?? null,
            'charge_id' => $payload['data']['object']['charge'] ?? null,
        ]);
    }

    /**
     * Sincroniza/cria a linha em `subscriptions` com o snapshot do plano.
     *
     * @param array<string, mixed> $stripeSubscription
     */
    private function syncSubscriptionSnapshot(Tenant $tenant, array $stripeSubscription): void
    {
        $planCode = $stripeSubscription['metadata']['plan_code'] ?? null;
        $plan = $planCode ? Plan::where('code', $planCode)->first() : null;

        $snapshot = $plan
            ? $plan->only(['code', 'name', 'base_price_cents', 'included_professionals', 'included_ai_messages'])
            : [];

        DB::table('subscriptions')
            ->updateOrInsert(
                ['tenant_id' => $tenant->id, 'stripe_id' => $stripeSubscription['id']],
                [
                    'type' => 'default',
                    'stripe_status' => $stripeSubscription['status'] ?? 'active',
                    'stripe_price' => $stripeSubscription['items']['data'][0]['price']['id'] ?? null,
                    'quantity' => $stripeSubscription['items']['data'][0]['quantity'] ?? 1,
                    'plan_id' => $plan?->id,
                    'plan_snapshot' => json_encode($snapshot),
                    'professionals_quantity' => (int) ($stripeSubscription['metadata']['professionals_quantity'] ?? 1),
                    'current_period_start' => isset($stripeSubscription['current_period_start'])
                        ? Carbon::createFromTimestamp($stripeSubscription['current_period_start'])
                        : null,
                    'current_period_end' => isset($stripeSubscription['current_period_end'])
                        ? Carbon::createFromTimestamp($stripeSubscription['current_period_end'])
                        : null,
                    'trial_ends_at' => isset($stripeSubscription['trial_end'])
                        ? Carbon::createFromTimestamp($stripeSubscription['trial_end'])
                        : null,
                    'ends_at' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

        if ($plan) {
            $tenant->plan_id = $plan->id;
            $tenant->save();
        }
    }

    private function findTenantByStripeCustomerId(string $customerId): ?Tenant
    {
        return Tenant::query()
            ->withoutGlobalScopes()
            ->where('stripe_customer_id', $customerId)
            ->first();
    }
}
