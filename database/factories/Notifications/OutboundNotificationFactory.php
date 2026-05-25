<?php

namespace Database\Factories\Notifications;

use App\Domain\Messaging\Notification\Enums\NotificationSkipReason;
use App\Domain\Messaging\Notification\Enums\NotificationStatus;
use App\Domain\Messaging\Notification\Enums\NotificationType;
use App\Domain\Messaging\Notification\Models\OutboundNotification;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Feature 013 — Factory para `OutboundNotification`.
 *
 * @extends Factory<OutboundNotification>
 */
class OutboundNotificationFactory extends Factory
{
    protected $model = OutboundNotification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'patient_id' => Paciente::factory(),
            'notification_type' => NotificationType::AppointmentConfirmation,
            'milestone' => 't_minus_24h',
            'status' => NotificationStatus::Queued,
            'skip_reason' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state([
            'status' => NotificationStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state([
            'status' => NotificationStatus::Delivered,
            'sent_at' => now()->subMinute(),
            'delivered_at' => now(),
        ]);
    }

    public function pendingManual(NotificationSkipReason $reason = NotificationSkipReason::NoChannel): static
    {
        return $this->state([
            'status' => NotificationStatus::PendingManual,
            'skip_reason' => $reason,
        ]);
    }

    public function skipped(NotificationSkipReason $reason = NotificationSkipReason::OptOut): static
    {
        return $this->state([
            'status' => NotificationStatus::Skipped,
            'skip_reason' => $reason,
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
