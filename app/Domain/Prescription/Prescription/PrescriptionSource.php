<?php

namespace App\Domain\Prescription\Prescription;

enum PrescriptionSource: string
{
    case Manual = 'manual';

    case Import = 'import';

    case Ai = 'ai';
}
