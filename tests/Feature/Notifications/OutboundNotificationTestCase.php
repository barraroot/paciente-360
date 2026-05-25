<?php

namespace Tests\Feature\Notifications;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Channel\Models\ChannelTemplate;
use App\Domain\Messaging\Notification\DataTransfer\NotificationRequest;
use App\Domain\Messaging\Notification\Enums\NotificationType;
use App\Domain\Messaging\Notification\Models\NotificationTemplate;
use App\Domain\Messaging\Notification\Services\OutboundNotificationDispatcher;
use App\Models\Paciente;
use App\Models\Tenant;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * Feature 013 — Base para os gates de notificação outbound.
 *
 * Fornece helpers para montar o cenário feliz (tenant + WhatsApp ativo +
 * paciente com telefone + template configurado + ChannelTemplate aprovado).
 */
abstract class OutboundNotificationTestCase extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Redis::flushdb();
        Queue::fake(); // evita execução real de SendOutboundMessageJob
    }

    protected function dispatcher(): OutboundNotificationDispatcher
    {
        return app(OutboundNotificationDispatcher::class);
    }

    protected function makePatient(Tenant $tenant, string $phone = '+5511988887777'): Paciente
    {
        return Paciente::factory()->create([
            'tenant_id' => $tenant->id,
            'telefone_primario' => $phone,
        ]);
    }

    protected function makeWhatsAppChannel(Tenant $tenant, string $status = 'ativo'): Channel
    {
        return Channel::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => 'whatsapp',
            'status' => $status,
        ]);
    }

    protected function makeTemplate(
        Tenant $tenant,
        NotificationType $type = NotificationType::AppointmentConfirmation,
        string $providerTemplateId = 'HXabc123',
    ): NotificationTemplate {
        return NotificationTemplate::factory()->create([
            'tenant_id' => $tenant->id,
            'notification_type' => $type,
            'channel_type' => 'whatsapp',
            'provider_template_id' => $providerTemplateId,
            'variables_map' => ['patient_name' => 'patient_name'],
        ]);
    }

    protected function makeApprovedChannelTemplate(
        Tenant $tenant,
        Channel $channel,
        string $providerTemplateId = 'HXabc123',
    ): ChannelTemplate {
        return ChannelTemplate::factory()->approved()->create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'provider_template_id' => $providerTemplateId,
        ]);
    }

    protected function confirmationRequest(Tenant $tenant, Paciente $patient, string $milestone = 't_minus_24h'): NotificationRequest
    {
        return new NotificationRequest(
            tenantId: $tenant->id,
            patientId: $patient->id,
            type: NotificationType::AppointmentConfirmation,
            milestone: $milestone,
            sourceType: 'appointment',
            sourceId: 1,
            context: ['patient_name' => $patient->nome ?? 'Paciente'],
        );
    }
}
