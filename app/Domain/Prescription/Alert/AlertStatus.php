<?php

namespace App\Domain\Prescription\Alert;

enum AlertStatus: string
{
    case Pending = 'pending';

    case Dispatched = 'dispatched';

    case BlockedNoChannel = 'blocked_no_channel';

    case BlockedNoTemplate = 'blocked_no_template';

    case Skipped = 'skipped';

    case Cancelled = 'cancelled';

    case Failed = 'failed';
}
