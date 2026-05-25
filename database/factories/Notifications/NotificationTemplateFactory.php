<?php

namespace Database\Factories\Notifications;

use App\Domain\Messaging\Notification\Enums\NotificationType;
use App\Domain\Messaging\Notification\Models\NotificationTemplate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Feature 013 — Factory para `NotificationTemplate`.
 *
 * @extends Factory<NotificationTemplate>
 */
class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'notification_type' => NotificationType::AppointmentConfirmation,
            'channel_type' => 'whatsapp',
            'provider_template_id' => 'HX'.Str::random(32),
            'language' => 'pt_BR',
            'variables_map' => [
                'patient_name' => 'patient_name',
                'appointment_datetime' => 'appointment_datetime',
            ],
            'is_active' => true,
        ];
    }

    public function ofType(NotificationType $type): static
    {
        return $this->state(['notification_type' => $type]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
