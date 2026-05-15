<?php

namespace App\Services\Agenda;

use App\Models\Professional;
use App\Models\Tenant;

/**
 * **T025** — Resolve o IANA timezone correto para um contexto (clarify nº 13).
 *
 * Regras:
 *  - Profissional com `timezone` populado → usa o do profissional.
 *  - Profissional sem `timezone` (NULL) → herda do `Tenant.timezone`.
 *  - Tenant sem timezone (raro, fallback de segurança) → `America/Sao_Paulo`.
 *
 * Idempotente, pure function — testável sem DB (basta passar instâncias).
 */
final class TimezoneResolverService
{
    public const FALLBACK_TIMEZONE = 'America/Sao_Paulo';

    public function forProfessional(Professional $professional): string
    {
        if ($professional->timezone !== null && $professional->timezone !== '') {
            return $professional->timezone;
        }

        return $this->forTenant($professional->tenant);
    }

    public function forTenant(?Tenant $tenant): string
    {
        if ($tenant === null) {
            return self::FALLBACK_TIMEZONE;
        }

        return $tenant->timezone ?: self::FALLBACK_TIMEZONE;
    }
}
