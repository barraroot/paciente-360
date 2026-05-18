<?php

namespace App\Domain\Prescription\Renewal;

enum InitiatedByType: string
{
    case Professional = 'professional';

    case Ai = 'ai';

    case Patient = 'patient';
}
