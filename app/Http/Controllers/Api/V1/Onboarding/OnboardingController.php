<?php

namespace App\Http\Controllers\Api\V1\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\OnboardingStepRequest;
use App\Http\Resources\OnboardingStateResource;
use App\Models\Tenant;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Endpoints do wizard de onboarding — US-1.2.
 *
 * Todos os métodos exigem `auth:sanctum` e a permissão `manage` da
 * `OnboardingPolicy` (somente `admin-clinica` e `super-admin`).
 *
 * Pipeline: Form Request → Controller (≤30 linhas) → Service → Resource.
 *
 * @group Onboarding
 *
 * @see App\Services\Onboarding\OnboardingService
 * @see App\Policies\OnboardingPolicy
 * @see specs/001-fundacao-multitenant/contracts/openapi.yaml § /onboarding
 */
final class OnboardingController extends Controller
{
    /**
     * Estado atual do wizard de onboarding.
     *
     * Retorna progresso e status de cada etapa para o tenant atual.
     */
    public function state(Request $request, OnboardingService $service): OnboardingStateResource
    {
        Gate::authorize('manage-onboarding');

        /** @var Tenant $tenant */
        $tenant = app('tenant');

        return OnboardingStateResource::make($service->getState($tenant));
    }

    /**
     * Marcar etapa como concluída.
     *
     * O payload varia por etapa (stepKey). Etapa fora desta fase retorna 409.
     */
    public function complete(string $stepKey, OnboardingStepRequest $request, OnboardingService $service): OnboardingStateResource
    {
        Gate::authorize('manage-onboarding');

        /** @var Tenant $tenant */
        $tenant = app('tenant');

        return OnboardingStateResource::make($service->completeStep($tenant, $stepKey, $request->all()));
    }

    /**
     * Pular etapa não-bloqueante do wizard.
     *
     * Etapas bloqueantes (required=true) não podem ser puladas e retornam 409.
     */
    public function skip(string $stepKey, Request $request, OnboardingService $service): OnboardingStateResource
    {
        Gate::authorize('manage-onboarding');

        /** @var Tenant $tenant */
        $tenant = app('tenant');

        return OnboardingStateResource::make($service->skipStep($tenant, $stepKey));
    }
}
