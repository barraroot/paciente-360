<?php

declare(strict_types=1);

namespace App\Domain\Ai\Persona\Services;

use App\Domain\Ai\Persona\Models\PersonaTestSession;

/**
 * DTO imutável do resultado de `PersonaTestSessionService::open()`.
 *
 * `plainTextToken` é devolvido UMA vez ao caller (FR-046) — depois fica
 * apenas o hash em `personal_access_tokens.token`.
 */
final readonly class PersonaTestSessionOpenResult
{
    public function __construct(
        public PersonaTestSession $session,
        public string $plainTextToken,
        public string $echoChannel,
    ) {}
}
