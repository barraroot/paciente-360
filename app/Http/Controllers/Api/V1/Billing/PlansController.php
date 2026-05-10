<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Controller de listagem de planos (T186).
 *
 * Rota pública — sem autenticação (`security: []` no OpenAPI).
 * Retorna todos os planos ativos para exibição na página de preços.
 *
 * @group Billing
 */
final class PlansController extends Controller
{
    /**
     * Catálogo de planos disponíveis.
     *
     * Lista todos os planos ativos para exibição na landing page de preços.
     *
     * @unauthenticated
     *
     * @response 200 scenario="planos ativos" [{"id": 1, "code": "starter", "name": "Starter"}]
     */
    public function __invoke(SubscriptionService $subscriptionService): AnonymousResourceCollection
    {
        return PlanResource::collection($subscriptionService->listPlans());
    }
}
