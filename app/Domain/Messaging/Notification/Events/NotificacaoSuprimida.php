<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Notification\Events;

use App\Domain\Messaging\Notification\Models\OutboundNotification;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Feature 013 — Notificação suprimida antes do envio (opt-out ou debounce).
 *
 * Emitida mesmo quando o envio é suprimido, para preservar a trilha de auditoria
 * (FR-012). Payload sem PII clínica.
 *
 * @see specs/013-outbound-notifications/contracts/outbound-notifications.md §6
 */
final class NotificacaoSuprimida implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly OutboundNotification $notification,
    ) {}

    public function auditAction(): string
    {
        return 'notification.suppressed';
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'notification_id' => $this->notification->id,
            'patient_id' => $this->notification->patient_id,
            'type' => $this->notification->notification_type->value,
            'milestone' => $this->notification->milestone,
            'reason' => $this->notification->skip_reason?->value,
        ];
    }

    public function auditableModel(): ?Model
    {
        return $this->notification;
    }

    public function auditTenantId(): ?int
    {
        return $this->notification->tenant_id;
    }
}
