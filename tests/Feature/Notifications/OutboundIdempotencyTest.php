<?php

namespace Tests\Feature\Notifications;

use App\Domain\Messaging\Notification\Enums\NotificationStatus;
use App\Domain\Messaging\Notification\Models\OutboundNotification;
use Illuminate\Support\Facades\Redis;

/**
 * Gate G6 — mesmo (paciente,tipo,marco,data) não duplica; marcos distintos são envios distintos.
 */
class OutboundIdempotencyTest extends OutboundNotificationTestCase
{
    public function test_same_milestone_same_day_is_not_duplicated(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-idem', 'medico');
        $channel = $this->makeWhatsAppChannel($tenant);
        $this->makeTemplate($tenant);
        $this->makeApprovedChannelTemplate($tenant, $channel);
        $patient = $this->makePatient($tenant);

        $first = $this->dispatcher()->dispatch($this->confirmationRequest($tenant, $patient, 't_minus_24h'));
        // Isola o gate de idempotência do debounce 4h (que é por paciente+tipo):
        // em produção os marcos estão a >4h de distância.
        Redis::flushdb();
        $second = $this->dispatcher()->dispatch($this->confirmationRequest($tenant, $patient, 't_minus_24h'));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(
            1,
            OutboundNotification::withoutTenantScope()
                ->where('patient_id', $patient->id)
                ->where('notification_type', 'appointment_confirmation')
                ->where('milestone', 't_minus_24h')
                ->where('status', '<>', NotificationStatus::Skipped->value)
                ->count(),
        );
    }

    public function test_distinct_milestones_are_separate_notifications(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-idem2', 'medico');
        $channel = $this->makeWhatsAppChannel($tenant);
        $this->makeTemplate($tenant);
        $this->makeApprovedChannelTemplate($tenant, $channel);
        $patient = $this->makePatient($tenant);

        $t24 = $this->dispatcher()->dispatch($this->confirmationRequest($tenant, $patient, 't_minus_24h'));
        Redis::flushdb();
        $t2 = $this->dispatcher()->dispatch($this->confirmationRequest($tenant, $patient, 't_minus_2h'));

        $this->assertNotSame($t24->id, $t2->id);
        $this->assertSame(2, OutboundNotification::withoutTenantScope()->where('patient_id', $patient->id)->count());
    }
}
