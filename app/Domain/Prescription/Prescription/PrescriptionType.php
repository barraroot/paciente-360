<?php

namespace App\Domain\Prescription\Prescription;

enum PrescriptionType: string
{
    case Common = 'common';

    case Special = 'special';

    case Controlled = 'controlled';

    public function maxItems(): int
    {
        return $this === self::Controlled ? 1 : 10;
    }
}
