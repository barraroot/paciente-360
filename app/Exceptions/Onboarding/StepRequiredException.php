<?php

namespace App\Exceptions\Onboarding;

use RuntimeException;

/**
 * Lançada quando o usuário tenta pular um step marcado como `required`.
 * Mapeada para HTTP 409 no `bootstrap/app.php`.
 *
 * @see App\Services\Onboarding\OnboardingService
 */
final class StepRequiredException extends RuntimeException
{
    public function __construct(string $stepKey)
    {
        parent::__construct("Onboarding step '{$stepKey}' is required and cannot be skipped.");
    }
}
