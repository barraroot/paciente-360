<?php

namespace App\Services\Billing;

use App\Events\Billing\CheckoutCreated;
use App\Exceptions\Billing\AlreadySubscribedException;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;

/**
 * Serviço de criação de sessão Stripe Checkout (T185).
 *
 * Responsável por:
 *  - Validar que o tenant não tem assinatura ativa.
 *  - Garantir que o tenant existe como customer no Stripe.
 *  - Criar a sessão de checkout com os parâmetros corretos.
 *  - Emitir evento auditável `subscription.checkout.created`.
 *
 * NÃO armazena dados de cartão (Princípio VII — tokenização pelo gateway).
 * NÃO confia no client para status de pagamento (Princípio VII).
 */
final class CheckoutService
{
    public function __construct(
        private readonly StripeClientWrapper $stripeClient,
    ) {}

    /**
     * Cria uma sessão Stripe Checkout para o tenant.
     *
     * @return array{checkout_url: string, expires_at: int}
     *
     * @throws AlreadySubscribedException se o tenant já tem assinatura ativa.
     * @throws ModelNotFoundException se o plano não existe.
     */
    public function createSession(Tenant $tenant, string $planCode, int $professionals): array
    {
        // Verifica assinatura ativa (stripe_status active/trialing).
        $hasActive = $tenant->subscriptions()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->whereNull('ends_at')
            ->exists();

        if ($hasActive) {
            throw new AlreadySubscribedException;
        }

        $plan = Plan::where('code', $planCode)->firstOrFail();

        // Garante que o tenant existe como customer no Stripe.
        if (! $tenant->stripe_customer_id) {
            $tenant->createAsStripeCustomer([
                'email' => $tenant->responsible_email,
                'name' => $tenant->name,
                'metadata' => ['tenant_id' => (string) $tenant->id],
            ]);
        }

        $suffix = (string) config('tenancy.subdomain_suffix', 'lvh.me');
        $baseUrl = "https://{$tenant->slug}.{$suffix}";

        $session = $this->stripeClient->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $tenant->stripe_customer_id,
            'line_items' => [
                [
                    'price' => $plan->stripe_price_id_base,
                    'quantity' => $professionals,
                ],
            ],
            'success_url' => "{$baseUrl}/billing/success?session_id={CHECKOUT_SESSION_ID}",
            'cancel_url' => "{$baseUrl}/billing/cancel",
            'metadata' => [
                'tenant_id' => (string) $tenant->id,
                'plan_code' => $planCode,
                'professionals_quantity' => (string) $professionals,
            ],
            'subscription_data' => [
                'metadata' => [
                    'tenant_id' => (string) $tenant->id,
                    'plan_code' => $planCode,
                    'professionals_quantity' => (string) $professionals,
                ],
            ],
        ]);

        Event::dispatch(new CheckoutCreated($tenant, $planCode, $professionals));

        return [
            'checkout_url' => $session->url,
            'expires_at' => $session->expires_at,
        ];
    }
}
