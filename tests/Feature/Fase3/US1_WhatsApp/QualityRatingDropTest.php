<?php

namespace Tests\Feature\Fase3\US1_WhatsApp;

use App\Domain\Messaging\Channel\Events\CanalDegradado;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Channel\Services\QualityRatingMonitor;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T063 — Quality Rating drop webhook (AC-4.1.5, AC-4.1.8, NC-17).
 *
 * Quando Twilio reporta queda de Quality Rating (Low/Flagged),
 * sistema desabilita envios automáticos e dispara evento.
 *
 * TDD RED: QualityRatingMonitor service ainda não existe.
 */
class QualityRatingDropTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('clinica-quality', 'admin-clinica');

        $this->channel = Channel::factory()
            ->whatsapp()
            ->forTenant($this->tenant)
            ->create([
                'status' => 'ativo',
                'quality_rating' => 'high',
                'auto_send_disabled' => false,
            ]);
    }

    /** @test */
    public function test_quality_rating_low_webhook_disables_auto_send_and_fires_canal_degradado_event(): void
    {
        Event::fake([CanalDegradado::class]);

        // Simula chamada do QualityRatingMonitor service
        $monitor = app(QualityRatingMonitor::class);
        $monitor->onQualityChanged($this->channel, 'low');

        // Verifica que channel foi atualizado
        $this->channel->refresh();
        $this->assertEquals('low', $this->channel->quality_rating);
        $this->assertTrue($this->channel->auto_send_disabled);

        // Verifica evento
        Event::assertDispatched(CanalDegradado::class);

        // Verifica audit log
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'action' => 'channel.degraded',
        ]);
    }

    /** @test */
    public function test_quality_rating_back_to_medium_reenables_auto_send(): void
    {
        // Setup: channel com quality_rating = low e auto_send_disabled = true
        $this->channel->update([
            'quality_rating' => 'low',
            'auto_send_disabled' => true,
        ]);

        Event::fake([CanalDegradado::class]);

        // Simula melhoria de rating
        $monitor = app(QualityRatingMonitor::class);
        $monitor->onQualityChanged($this->channel, 'medium');

        $this->channel->refresh();
        $this->assertEquals('medium', $this->channel->quality_rating);
        $this->assertFalse($this->channel->auto_send_disabled);
    }

    /** @test */
    public function test_channel_with_low_quality_rejects_automatic_dispatch_but_allows_manual(): void
    {
        $this->markTestSkipped(
            'depends on MessageDispatchService — Lote D US4'
        );
    }
}
