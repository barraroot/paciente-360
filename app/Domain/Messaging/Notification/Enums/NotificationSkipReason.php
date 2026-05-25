<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Notification\Enums;

/**
 * Motivo pelo qual uma notificação foi suprimida (`skipped`) ou roteada
 * para contato manual (`pending_manual`) — feature 013.
 *
 * @see specs/013-outbound-notifications/data-model.md
 */
enum NotificationSkipReason: string
{
    case OptOut = 'opt_out';
    case Debounced = 'debounced';
    case NoChannel = 'no_channel';
    case NoTemplate = 'no_template';
    case SendFailed = 'send_failed';
}
