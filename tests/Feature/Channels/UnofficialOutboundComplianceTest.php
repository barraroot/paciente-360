<?php

namespace Tests\Feature\Channels;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Notification\DataTransfer\NotificationRequest;
use App\Domain\Messaging\Notification\Enums\NotificationStatus;
use App\Domain\Messaging\Notification\Enums\NotificationType;
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
 * Gate G5 (US4, Princípio VI) — no provedor NÃO oficial (Evolution), proativo
 * fora da janela de 24h é bloqueado → pending_manual (reuso do gate da Fase 13:
 * sem ChannelTemplate aprovado); dentro da janela, texto livre é permitido.
 */
class UnofficialOutboundComplianceTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Redis::flushdb();
        Queue::fake();
        [$this->tenant] = $this->tenantAndUserForRole('clinica-compliance', 'medico');
    }

    private function dispatcher(): OutboundNotificationDispatcher
    {
        return app(OutboundNotificationDispatcher::class);
    }

    private function evolutionChannel(): Channel
    {
        return Channel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'whatsapp',
            'provider' => 'evolution',
            'status' => 'ativo',
            'provider_metadata' => ['instance_name' => 'tenant_compliance'],
        ]);
    }

    private function request(Paciente $patient, ?string $freeForm = null): NotificationRequest
    {
        return new NotificationRequest(
            tenantId: $this->tenant->id,
            patientId: $patient->id,
            type: NotificationType::AppointmentConfirmation,
            milestone: 't_minus_24h',
            sourceType: 'appointment',
            sourceId: 1,
            context: ['patient_name' => 'Paciente'],
            freeFormBody: $freeForm,
        );
    }

    public function test_proactive_outside_window_on_evolution_is_pending_manual(): void
    {
        $this->evolutionChannel();
        $patient = Paciente::factory()->create([
            'tenant_id' => $this->tenant->id,
            'telefone_primario' => '+5511988887777',
        ]);

        // Sem conversa recente → fora da janela; Evolution não tem template aprovado.
        $notification = $this->dispatcher()->dispatch($this->request($patient));

        $this->assertSame(NotificationStatus::PendingManual, $notification->status);
        $this->assertSame('no_template', $notification->skip_reason->value);
    }

    public function test_within_window_free_text_is_sent_on_evolution(): void
    {
        $channel = $this->evolutionChannel();
        $patient = Paciente::factory()->create([
            'tenant_id' => $this->tenant->id,
            'telefone_primario' => '+5511988887777',
        ]);

        // Conversa com inbound recente → janela aberta.
        Conversation::create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $channel->id,
            'patient_id' => $patient->id,
            'external_thread_id' => $patient->fresh()->telefone_primario_normalizado,
            'status' => 'aberta',
            'opened_at' => now(),
            'priority' => 'normal',
            'last_inbound_message_at' => now()->subHour(),
            'received_outside_hours' => false,
            'unread_count' => 0,
        ]);

        $notification = $this->dispatcher()->dispatch($this->request($patient, freeForm: 'Olá! Confirmando sua consulta.'));

        $this->assertSame(NotificationStatus::Sent, $notification->status);
        $this->assertNull($notification->notification_template_id);
    }
}
