<?php

namespace Tests\Feature\Fase3\US3_Widget;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Widget\Models\WebWidgetConfig;
use App\Domain\Messaging\Widget\Models\WebWidgetSession;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T207 — Feature tests para comportamento fora do horário de atendimento.
 *
 * Cobre AC-4.3.5 e NC-10.d: 3 modos (bloqueia, fila, normal).
 */
class WidgetOutsideHoursTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private Channel $webChannel;

    private WebWidgetConfig $widgetConfig;

    private WebWidgetSession $session;

    private string $publicKey;

    private string $visitorToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('clinica-hours', 'admin-clinica');

        $this->publicKey = bin2hex(random_bytes(32));
        $this->visitorToken = bin2hex(random_bytes(32));

        $this->webChannel = Channel::factory()
            ->forTenant($this->tenant)
            ->web()
            ->create();

        $this->widgetConfig = WebWidgetConfig::factory()
            ->forTenant($this->tenant)
            ->create([
                'channel_id' => $this->webChannel->id,
                'public_key' => $this->publicKey,
                'allowed_origins' => [],
                'business_hours' => [
                    'monday' => '08:00-18:00',
                    'tuesday' => '08:00-18:00',
                    'wednesday' => '08:00-18:00',
                    'thursday' => '08:00-18:00',
                    'friday' => '08:00-18:00',
                    'saturday' => null,
                    'sunday' => null,
                    'timezone' => 'America/Sao_Paulo',
                ],
                'outside_hours_behavior' => 'bloqueia',
                'pre_chat_form' => 'opcional',
            ]);

        $this->session = WebWidgetSession::factory()->create([
            'tenant_id' => $this->tenant->id,
            'widget_config_id' => $this->widgetConfig->id,
            'visitor_token' => $this->visitorToken,
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'expires_at' => now()->addDays(30),
        ]);
    }

    private function messagesUrl(): string
    {
        return "/widget/v1/{$this->publicKey}/messages";
    }

    /** @test */
    public function test_bloqueia_mode_rejects_message_outside_business_hours(): void
    {
        $this->widgetConfig->update(['outside_hours_behavior' => 'bloqueia']);

        // Saturday 22:00 BRT — outside all business hours
        Carbon::setTestNow(Carbon::parse('2026-05-16 01:00:00', 'UTC')); // Saturday 22:00 BRT = Sun 01:00 UTC

        $response = $this->withHeaders([
            'X-Visitor-Token' => $this->visitorToken,
        ])->postJson($this->messagesUrl(), [
            'content_type' => 'text',
            'body' => 'Olá fora do horário!',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('error', 'outside_business_hours');

        Carbon::setTestNow(null);
    }

    /** @test */
    public function test_fila_mode_accepts_message_outside_hours_and_marks_received_outside_hours(): void
    {
        $this->widgetConfig->update(['outside_hours_behavior' => 'fila']);

        // Saturday 22:00 BRT
        Carbon::setTestNow(Carbon::parse('2026-05-16 01:00:00', 'UTC'));

        $response = $this->withHeaders([
            'X-Visitor-Token' => $this->visitorToken,
        ])->postJson($this->messagesUrl(), [
            'content_type' => 'text',
            'body' => 'Mensagem fila!',
        ]);

        $response->assertCreated();

        // Conversation should be marked as received_outside_hours
        $this->assertDatabaseHas('messaging_conversations', [
            'tenant_id' => $this->tenant->id,
            'received_outside_hours' => true,
        ]);

        Carbon::setTestNow(null);
    }

    /** @test */
    public function test_normal_mode_accepts_messages_regardless_of_hours(): void
    {
        $this->widgetConfig->update(['outside_hours_behavior' => 'normal']);

        // Saturday 22:00 BRT
        Carbon::setTestNow(Carbon::parse('2026-05-16 01:00:00', 'UTC'));

        $response = $this->withHeaders([
            'X-Visitor-Token' => $this->visitorToken,
        ])->postJson($this->messagesUrl(), [
            'content_type' => 'text',
            'body' => 'Mensagem no modo normal!',
        ]);

        $response->assertCreated();

        Carbon::setTestNow(null);
    }

    /** @test */
    public function test_inside_business_hours_always_accepts(): void
    {
        $this->widgetConfig->update(['outside_hours_behavior' => 'bloqueia']);

        // Monday 10:00 BRT = Monday 13:00 UTC
        Carbon::setTestNow(Carbon::parse('2026-05-11 13:00:00', 'UTC'));

        $response = $this->withHeaders([
            'X-Visitor-Token' => $this->visitorToken,
        ])->postJson($this->messagesUrl(), [
            'content_type' => 'text',
            'body' => 'Mensagem dentro do horário!',
        ]);

        $response->assertCreated();

        Carbon::setTestNow(null);
    }
}
