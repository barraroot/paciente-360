<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource do estado do wizard de onboarding.
 *
 * Serializa o shape `{ completed, progress_percent, steps }` conforme
 * definido no OpenAPI `§ OnboardingStateResource`. O campo `label` de cada
 * step é traduzido via `__()` antes de serializar.
 *
 * O `$resource` é um `array` retornado por `OnboardingService::getState()`.
 *
 * @see App\Services\Onboarding\OnboardingService::getState()
 * @see specs/001-fundacao-multitenant/contracts/openapi.yaml § OnboardingStateResource
 */
final class OnboardingStateResource extends JsonResource
{
    /**
     * @return array{
     *     completed: bool,
     *     progress_percent: int,
     *     steps: list<array{key: string, label: string, status: string, required: bool}>
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array{completed: bool, progress_percent: int, steps: list<array{key: string, label: string, status: string, required: bool}>} $state */
        $state = $this->resource;

        return [
            'completed' => (bool) $state['completed'],
            'progress_percent' => (int) $state['progress_percent'],
            'steps' => $state['steps'],
        ];
    }
}
