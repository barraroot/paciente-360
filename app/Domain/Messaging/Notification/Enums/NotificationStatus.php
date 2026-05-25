<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Notification\Enums;

/**
 * Estado de uma notificação outbound ao longo do ciclo de entrega (feature 013).
 *
 * Transições:
 *   queued ──► sent ──► delivered            (sucesso; via status callback)
 *      │         └────► failed ──► pending_manual   (falha definitiva pós-retry)
 *      ├──► skipped(opt_out | debounced)            (terminal, antes de resolver canal)
 *      └──► pending_manual(no_channel | no_template | send_failed)  (terminal acionável)
 *
 * @see specs/013-outbound-notifications/data-model.md
 */
enum NotificationStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case PendingManual = 'pending_manual';
    case Skipped = 'skipped';

    /**
     * Estados terminais (não transicionam mais).
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Delivered, self::PendingManual, self::Skipped => true,
            default => false,
        };
    }
}
