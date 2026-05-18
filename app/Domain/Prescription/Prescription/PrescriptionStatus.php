<?php

namespace App\Domain\Prescription\Prescription;

enum PrescriptionStatus: string
{
    case Active = 'active';

    case Cancelled = 'cancelled';

    case Superseded = 'superseded';
}
