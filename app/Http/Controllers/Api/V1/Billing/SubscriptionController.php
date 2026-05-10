<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\SubscriptionPatchRequest;
use App\Http\Resources\SubscriptionResource;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller de assinatura do tenant (T186 + T202).
 *
 * GET  /billing/subscription — leitura da assinatura atual.
 * PATCH /billing/subscription — upgrade/downgrade de plano ou quantidade.
 *
 * @group Billing
 */
final class SubscriptionController extends Controller
{
    /**
     * Estado atual da assinatura.
     *
     * Retorna 404 se o tenant ainda estiver em trial sem assinatura formal.
     *
     * @response 200 scenario="assinatura ativa" {"id": 1, "stripe_status": "active"}
     * @response 404 scenario="sem assinatura" {"error": "subscription_not_found"}
     */
    public function show(Request $request, SubscriptionService $subscriptionService): JsonResponse|SubscriptionResource
    {
        $tenant = app('tenant');

        $subscription = $subscriptionService->getCurrent($tenant);

        if ($subscription === null) {
            return response()->json([
                'error' => 'subscription_not_found',
                'message' => 'Nenhuma assinatura ativa encontrada.',
            ], 404);
        }

        return SubscriptionResource::make($subscription);
    }

    /**
     * Upgrade ou downgrade de plano/quantidade.
     *
     * Aumentos disparam proration imediata; reduções vigoram no próximo ciclo.
     *
     * @response 200 scenario="atualizado" {"id": 1, "stripe_status": "active"}
     * @response 502 scenario="falha no Stripe" {"message": "Falha ao comunicar com gateway."}
     */
    public function patch(SubscriptionPatchRequest $request, SubscriptionService $subscriptionService): SubscriptionResource
    {
        $tenant = app('tenant');

        $subscription = $subscriptionService->updateSubscription($tenant, $request->validated());

        return SubscriptionResource::make($subscription)
            ->additional(['proration_preview' => $subscription->proration_preview ?? null]);
    }
}
