<?php

namespace App\Services\Billing;

use App\Events\Billing\SubscriptionUpdated;
use App\Exceptions\Billing\BillingGatewayException;
use App\Exceptions\Billing\NoActiveSubscriptionException;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Subscription;
use Stripe\Exception\ApiErrorException;

/**
 * Serviço de consulta e atualização de assinatura (T186 + T202).
 */
final class SubscriptionService
{
    public function __construct(
        private readonly StripeClientWrapper $stripeClient,
    ) {}

    /**
     * Retorna a assinatura ativa do tenant, ou null se não houver.
     */
    public function getCurrent(Tenant $tenant): ?Subscription
    {
        return $tenant->subscriptions()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->whereNull('ends_at')
            ->first();
    }

    /**
     * Lista todos os planos públicos/ativos.
     *
     * @return Collection<int, Plan>
     */
    public function listPlans(): Collection
    {
        return Plan::where('is_active', true)->get();
    }

    /**
     * Atualiza a assinatura ativa do tenant (upgrade ou downgrade).
     *
     * Regras de proration (FR-015, FR-016):
     *  - Upgrade (aumento de qty ou troca para plano mais caro) → `create_prorations` por padrão.
     *  - Downgrade (redução de qty ou troca para plano mais barato) → `none` por padrão.
     *    O novo plano é aplicado imediatamente sem estorno ("downgrade preguiçoso");
     *    recursos não são bloqueados durante o ciclo restante.
     *  - O cliente pode sobrescrever via `proration_behavior` no payload.
     *
     * Chamadas ao Stripe são feitas via `StripeClientWrapper` (não pelo Cashier interno),
     * permitindo mock completo em testes sem chave de API real.
     *
     * @param array<string, mixed> $payload
     *
     * @throws NoActiveSubscriptionException se não houver assinatura ativa (404).
     * @throws BillingGatewayException se o Stripe retornar erro (502).
     */
    public function updateSubscription(Tenant $tenant, array $payload): Subscription
    {
        $subscription = $this->getCurrent($tenant);

        if ($subscription === null) {
            throw new NoActiveSubscriptionException;
        }

        $currentPlan = Plan::find($subscription->plan_id);
        $newPlan = isset($payload['plan_code'])
            ? Plan::where('code', $payload['plan_code'])->firstOrFail()
            : null;

        $oldQty = $subscription->professionals_quantity;
        $newQty = $payload['professionals_quantity'] ?? $oldQty;

        $planChanged = $newPlan !== null && $newPlan->id !== ($currentPlan?->id);
        $qtyChanged = $newQty !== $oldQty;

        // Determina se é upgrade ou downgrade.
        $isPlanUpgrade = $planChanged
            && $newPlan->base_price_cents > ($currentPlan?->base_price_cents ?? 0);

        $isQtyUpgrade = $qtyChanged && $newQty > $oldQty;

        $isUpgrade = $isPlanUpgrade || $isQtyUpgrade;

        // Proration behavior: cliente pode sobrescrever; default varia por direção.
        $prorationBehavior = $payload['proration_behavior']
            ?? ($isUpgrade ? 'create_prorations' : 'none');

        // Monta params para o Stripe subscriptions->update.
        $stripeParams = [
            'proration_behavior' => $prorationBehavior,
        ];

        if ($planChanged) {
            $stripeParams['items'] = [
                ['price' => $newPlan->stripe_price_id_base, 'quantity' => $newQty],
            ];
        } elseif ($qtyChanged) {
            $stripeParams['quantity'] = $newQty;
        }

        try {
            $this->stripeClient->client->subscriptions->update(
                $subscription->stripe_id,
                $stripeParams,
            );
        } catch (ApiErrorException $e) {
            throw new BillingGatewayException($e->getMessage());
        }

        // Atualiza campos locais após confirmação do Stripe.
        $planToSave = $newPlan ?? $currentPlan;
        $subscription->plan_id = $planToSave?->id ?? $subscription->plan_id;
        $subscription->professionals_quantity = $newQty;
        $subscription->quantity = $newQty;

        if ($planToSave !== null) {
            $subscription->plan_snapshot = $planToSave->only([
                'code',
                'name',
                'base_price_cents',
                'included_professionals',
                'included_ai_messages',
            ]);
        }

        $subscription->save();
        $subscription->refresh();

        // Proration preview apenas quando prorations estão ativas.
        // Com proration_behavior=none, não há preview relevante.
        $prorationPreview = null;

        if ($prorationBehavior === 'create_prorations') {
            try {
                $upcoming = $this->stripeClient->client->invoices->upcoming([
                    'customer' => $tenant->stripe_customer_id,
                ]);

                $prorationPreview = [
                    'amount_due_cents' => $upcoming->amount_due ?? null,
                    'currency' => $upcoming->currency ?? null,
                ];
            } catch (ApiErrorException) {
                // Preview é não-crítico; falha silenciosa.
                $prorationPreview = null;
            }
        }

        // Auditoria FR-016.
        Event::dispatch(new SubscriptionUpdated($tenant, [
            'old_plan_code' => $currentPlan?->code,
            'new_plan_code' => $newPlan?->code,
            'old_qty' => $oldQty,
            'new_qty' => $newQty,
            'proration_behavior' => $prorationBehavior,
            'is_upgrade' => $isUpgrade,
            'proration_amount_cents' => $prorationPreview['amount_due_cents'] ?? null,
        ]));

        // Transient: proration_preview não é persistido (atribuído após save).
        $subscription->proration_preview = $prorationPreview;

        return $subscription;
    }
}
