<?php

namespace App\Domain\Prescription\Alert;

use Carbon\CarbonImmutable;

final class PrescriptionAlertIdempotencyKey
{
    public static function for(int $prescriptionId, AlertType $type, CarbonImmutable $date): string
    {
        return sprintf(
            'prescription_alert:%d:%s:%s',
            $prescriptionId,
            $type->value,
            $date->format('Y-m-d'),
        );
    }
}
