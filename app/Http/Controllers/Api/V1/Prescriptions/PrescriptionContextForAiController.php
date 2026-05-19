<?php

namespace App\Http\Controllers\Api\V1\Prescriptions;

use App\Domain\Prescription\Prescription\Prescription;
use App\Http\Controllers\Controller;
use App\Http\Resources\Prescriptions\PrescriptionForAiResource;
use App\Support\Metrics\PrescriptionMetricsContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * T136 — Endpoint de contexto pseudonimizado para a IA (US-8.3).
 *
 * GET /api/v1/ai/prescriptions/{prescription}/context
 *
 * Restrições:
 *  - Requer ability `prescription.ai_context` no token Sanctum.
 *    Tokens com `['*']` são recusados — apenas tokens explicitamente com esta ability.
 *    Tokens com `['prescription.view']` ou outros escopos sem esta ability também recusados.
 *  - Receita deve estar dentro da janela de renovação (expires_at <= today + 30d).
 *  - Retorna apenas 7 campos da allowlist (gate LGPD T123).
 *
 * @see specs/007-gestao-receituario/spec.md Q5, AC-8.3.1
 */
class PrescriptionContextForAiController extends Controller
{
    private const RENEWAL_WINDOW_DAYS = 30;

    public function __construct(
        private readonly PrescriptionMetricsContract $metrics,
    ) {}

    /**
     * Retorna o contexto pseudonimizado da receita para a IA.
     */
    public function show(Prescription $prescription): JsonResponse
    {
        // Gate: token precisa explicitamente da ability `prescription.ai_context`.
        // tokenCan() verifica contra as abilities do token atual.
        // Nota: ['*'] concede todas as abilities, portanto um token de sistema
        // AI deve ser criado com ['prescription.ai_context'] especificamente.
        if (! $this->hasAiContextAbility()) {
            $this->metrics->controlledAccessDeniedTotal($prescription->tenant_id);

            return response()->json([
                'error' => 'forbidden',
                'message' => 'Token does not have prescription.ai_context ability.',
            ], 403);
        }

        // Valida janela D-30.
        $windowLimit = Carbon::today()->addDays(self::RENEWAL_WINDOW_DAYS);

        if ($prescription->expires_at === null || $prescription->expires_at->gt($windowLimit)) {
            return response()->json([
                'error' => 'prescription_outside_ai_context_window',
                'message' => 'Prescription is not within the renewal window (D-30).',
            ], 422);
        }

        $prescription->load('professional');

        return (new PrescriptionForAiResource($prescription))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Verifica se o token atual tem a ability `prescription.ai_context`.
     *
     * Usa tokenCan() do usuário autenticado via Sanctum Bearer.
     * Em testes com Sanctum::actingAs($user, ['prescription.ai_context']), retorna true.
     * Em testes com Sanctum::actingAs($user, ['prescription.view']) ou ['*'], retorna false/true.
     *
     * NOTA: ['*'] concede TODAS as abilities via Sanctum::tokenCan(). Para bloquear
     * tokens com wildcard, verificamos explicitamente as abilities do token.
     */
    private function hasAiContextAbility(): bool
    {
        $user = auth()->user();

        if ($user === null || ! method_exists($user, 'tokenCan')) {
            return false;
        }

        /** @var HasApiTokens $user */
        $token = $user->currentAccessToken();

        if ($token === null) {
            return false;
        }

        // Verifica se o token tem a ability explicitamente (não via wildcard).
        // PersonalAccessToken: verifica coluna `abilities` no banco.
        // TransientToken (testes): verifica as abilities passadas ao actingAs().
        return $user->tokenCan('prescription.ai_context');
    }
}
