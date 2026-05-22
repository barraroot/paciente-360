<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Privacy\Models\ForgettingRequest;
use App\Domain\Privacy\Models\PortabilityRequest;
use App\Models\User;

/**
 * **T056 (Fase 8 — Lote A US-13.2)** — Policy combinada para Forgetting e Portability.
 *
 * Padrão:
 *   - viewAny / view  → ability `privacy.view`
 *   - create          → liberado (paciente pode solicitar via formulário público;
 *                        admin lavra manualmente também — controle de auth fica nas rotas)
 *   - execute         → ability `forgetting.execute` ou `portability.execute`
 *
 * Cross-tenant: BelongsToTenant scope filtra; defesa em profundidade aqui valida
 * que tenant_id da request bate com user.
 */
class PrivacyRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('privacy.view');
    }

    public function view(User $user, ForgettingRequest|PortabilityRequest $request): bool
    {
        if (! $user->can('privacy.view')) {
            return false;
        }

        return $user->tenant_id === null || $request->tenant_id === $user->tenant_id;
    }

    public function execute(User $user, ForgettingRequest|PortabilityRequest $request): bool
    {
        $ability = $request instanceof ForgettingRequest ? 'forgetting.execute' : 'portability.execute';

        if (! $user->can($ability)) {
            return false;
        }

        return $user->tenant_id === null || $request->tenant_id === $user->tenant_id;
    }
}
