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

    /**
     * Retorna o AlertType a partir da quantidade de dias restantes.
     * Retorna null se não houver mapeamento (ex.: 30 dias).
     */
    public static function fromDays(int $days): ?self
    {
        return match ($days) {
            15 => self::Days15,
            7 => self::Days7,
            1 => self::Days1,
            default => null,
        };
    }
}
