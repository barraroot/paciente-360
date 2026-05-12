<?php

namespace Tests\Feature\Fase3\US6_Takeover;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Events\ConversaAssumidaPorHumano;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T165 — Feature tests para Reprise de Pausa (AC-4.6.2b).
 *
 * Quando IA já está pausada e atendente age novamente,
 * o timer é ESTENDIDO (não acumulado): ai_paused_until = now() + duração.
 */
class TakeoverRepriseTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $attendant;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->attendant] = $this->tenantAndUserForRole('clinica-takeover-reprise', 'atendente');

        $this->channel = Channel::factory()
            ->forTenant($this->tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    #[Test]
    public function second_takeover_extends_timer_does_not_accumulate(): void
    {
        Event::fake([ConversaAssumidaPorHumano::class]);

        // Conversation already paused for 10 minutes remaining
        $pausedUntil = now()->addMinutes(10);
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->aberta()
            ->create([
                'ai_paused_until' => $pausedUntil,
                'ai_pause_set_by' => $this->attendant->id,
            ]);

        // Second takeover (default 30 min)
        $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/takeover"),
            []
        )->assertStatus(200);

        $conversation->refresh();

        // Should be ~30 min from NOW, not 40min (10 remaining + 30)
        $expectedAt = now()->addMinutes(30);
        $diffSeconds = abs($conversation->ai_paused_until->diffInSeconds($expectedAt));
        $this->assertLessThan(60, $diffSeconds);

        // Must NOT be ~40 minutes (accumulation)
        $accumulatedAt = now()->addMinutes(40);
        $diffToAccumulated = abs($conversation->ai_paused_until->diffInSeconds($accumulatedAt));
        $this->assertGreaterThan(60, $diffToAccumulated, 'Timer should not accumulate');
    }

    #[Test]
    public function sending_message_during_active_pause_extends_timer(): void
    {
        // Conversation already paused for 10 minutes remaining
        $pausedUntil = now()->addMinutes(10);
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->aberta()
            ->comJanela24hAberta()
            ->create([
                'ai_paused_until' => $pausedUntil,
                'ai_pause_set_by' => $this->attendant->id,
            ]);

        // Send a message during active pause
        $this->withHeaders(['Idempotency-Key' => 'test-reprise-msg-'.$conversation->id])
            ->postJson(
                $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
                [
                    'content_type' => 'text',
                    'body' => 'Mensagem durante pausa ativa.',
                ]
            )->assertStatus(201);

        $conversation->refresh();

        // Should be extended to ~30 min from now (not 40 = 10 remaining + 30)
        $expectedAt = now()->addMinutes(30);
        $diffSeconds = abs($conversation->ai_paused_until->diffInSeconds($expectedAt));
        $this->assertLessThan(60, $diffSeconds);
    }

    #[Test]
    public function reprise_fires_new_conversa_assumida_por_humano_event_with_motivo_reprise(): void
    {
        Event::fake([ConversaAssumidaPorHumano::class]);

        $pausedUntil = now()->addMinutes(10);
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->aberta()
            ->create([
                'ai_paused_until' => $pausedUntil,
                'ai_pause_set_by' => $this->attendant->id,
            ]);

        $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/takeover"),
            []
        )->assertStatus(200);

        // When already paused, reprise should fire with motivo = 'reprise'
        Event::assertDispatched(ConversaAssumidaPorHumano::class, function (ConversaAssumidaPorHumano $event) use ($conversation): bool {
            return $event->conversation->id === $conversation->id
                && $event->reason === 'reprise';
        });
    }
}
