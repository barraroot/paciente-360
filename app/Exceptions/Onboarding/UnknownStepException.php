<?php

namespace App\Exceptions\Onboarding;

use RuntimeException;

/**
 * Lançada quando a chave de step não existe na definição de etapas do wizard.
 * Mapeada para HTTP 404 no `bootstrap/app.php`.
 *
 * @see App\Services\Onboarding\OnboardingService
 */
final class UnknownStepException extends RuntimeException
{
    public function __construct(string $stepKey)
    {
        parent::__construct("Onboarding step '{$stepKey}' not found.");
    }
}
