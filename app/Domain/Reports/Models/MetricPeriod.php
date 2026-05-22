<?php

declare(strict_types=1);

namespace App\Domain\Reports\Models;

enum MetricPeriod: string
{
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
}
