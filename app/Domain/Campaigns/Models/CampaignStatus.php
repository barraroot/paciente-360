<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Models;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Dispatching = 'dispatching';
    case Completed = 'completed';
    case Canceled = 'canceled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Canceled], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Scheduled => 'Agendada',
            self::Dispatching => 'Em disparo',
            self::Completed => 'Concluída',
            self::Canceled => 'Cancelada',
        };
    }
}
