<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\HardCapRequest;
use App\Http\Resources\AiUsageResource;
use App\Services\Billing\AiUsageService;
use App\Services\Billing\HardCapService;
use Illuminate\Http\Request;

/**
 * Controller de uso de IA (T222 + T223).
 *
 * GET  /billing/ai-usage           — leitura do meter atual.
 * PATCH /billing/ai-usage/hard-cap — configura ou remove hard cap.
 *
 * @group Billing
 */
final class AiUsageController extends Controller
{
    /**
     * Consumo de IA do ciclo atual.
     *
     * Retorna o meter de uso do mês corrente com projeção e dados de hard cap.
     *
     * @response 200 scenario="meter atual" {"year_month": "2026-05", "consumed": 150, "included_quota": 1000}
     */
    public function show(Request $request, AiUsageService $service): AiUsageResource
    {
        $tenant = app('tenant');
        $meter = $service->getCurrentMeter($tenant);

        return new AiUsageResource($service->buildResource($meter));
    }

    /**
     * Configurar ou remover hard cap de mensagens IA.
     *
     * Passe `hard_cap: null` para remover o limite. `hard_cap: 0` desliga
     * a IA imediatamente.
     *
     * @response 200 scenario="cap atualizado" {"hard_cap": 500, "consumed": 150}
     */
    public function patchHardCap(HardCapRequest $request, HardCapService $hardCapService, AiUsageService $aiUsageService): AiUsageResource
    {
        $tenant = app('tenant');
        $cap = $request->validated()['hard_cap'];

        $meter = $hardCapService->setHardCap($tenant, $cap);

        return new AiUsageResource($aiUsageService->buildResource($meter));
    }
}
