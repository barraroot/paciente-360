<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Models;

enum CampaignChannel: string
{
    case WhatsApp = 'whatsapp';
    case Instagram = 'instagram';
    case SmsFuture = 'sms_future';
}
