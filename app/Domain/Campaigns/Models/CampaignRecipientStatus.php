<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Models;

enum CampaignRecipientStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Responded = 'responded';
    case Blocked = 'blocked';
    case Failed = 'failed';
}
