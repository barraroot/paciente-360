<?php

namespace App\Exceptions\Onboarding;

use RuntimeException;

/**
 * Lançada quando o usuário tenta completar ou pular um step cujo status é
 * `locked`. Mapeada para HTTP 409 no `bootstrap/app.php`.
 *
 * @see App\Services\Onboarding\OnboardingService
 */
final class StepLockedException extends RuntimeException
{
    public function __construct(string $stepKey)
    {
        parent::__construct("Onboarding step '{$stepKey}' is locked.");
    }
}
