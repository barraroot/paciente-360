<?php

namespace App\Services\Agenda;

use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Exceção lançada por SlotReservationService.reserve() quando há conflito
 * (já existe reserva ativa OU constraint UNIQUE de Appointment bate).
 *
 * O controller traduz para HTTP 409.
 */
class SlotConflictException extends RuntimeException
{
    public function __construct(
        public readonly string $holderType,
        public readonly ?CarbonInterface $expiresAt,
    ) {
        parent::__construct('Slot já está reservado por outro holder.');
    }
}
