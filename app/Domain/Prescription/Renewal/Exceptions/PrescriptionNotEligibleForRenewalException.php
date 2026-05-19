<?php

namespace App\Domain\Prescription\Renewal\Exceptions;

use RuntimeException;

/**
 * T130 — Disparada quando uma receita não atende os critérios de renovação.
 *
 * Motivos possíveis:
 *  - `prescription_not_active` — status não é active.
 *  - `prescription_outside_renewal_window` — expires_at > today + 30d.
 *  - `prescription_already_renewed` — já existe renewal completa para esta original.
 */
final class PrescriptionNotEligibleForRenewalException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
    ) {
        parent::__construct("Prescription not eligible for renewal: {$reason}");
    }

    public static function forReason(string $reason): self
    {
        return new self($reason);
    }
}
