<?php

namespace App\Domain\Prescription\Alert;

enum AlertType: string
{
    case Days15 = 'days15';

    case Days7 = 'days7';

    case Days1 = 'days1';

    public function daysBefore(): int
    {
        return match ($this) {
            self::Days15 => 15,
            self::Days7 => 7,
            self::Days1 => 1,
        };
    }
}
