<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CheckoutRequest;
use App\Services\Billing\CheckoutService;
use Illuminate\Http\JsonResponse;

/**
 * Controller de checkout de assinatura (T185).
 *
 * Pipeline: `CheckoutRequest → CheckoutController → CheckoutService`.
 * Nenhuma regra de negócio aqui — apenas roteamento de I/O.
 *
 * @group Billing
 */
final class CheckoutController extends Controller
{
    /**
     * Criar sessão de checkout Stripe.
     *
     * Retorna a URL de checkout para o tenant assinar um plano.
     *
     * @response 200 scenario="sessão criada" {"checkout_url": "https://checkout.stripe.com/...", "expires_at": "2026-05-10T12:00:00Z"}
     * @response 409 scenario="assinatura já existe" {"message": "Tenant já possui assinatura ativa."}
     */
    public function __invoke(CheckoutRequest $request, CheckoutService $checkoutService): JsonResponse
    {
        $tenant = app('tenant');

        $result = $checkoutService->createSession(
            tenant: $tenant,
            planCode: $request->validated('plan_code'),
            professionals: (int) $request->validated('professionals_quantity'),
        );

        return response()->json($result, 200);
    }
}
