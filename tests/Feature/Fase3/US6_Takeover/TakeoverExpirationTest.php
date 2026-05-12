<?php

namespace Tests\Feature\Fase3\US6_Takeover;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Contracts\ConversaIATogglingContract;
use App\Domain\Messaging\Conversation\Events\ConversaRetomadaPelaIA;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Jobs\Messaging\ExpireAiPausesJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T166 — Feature tests para Expiração da Pausa de IA (AC-4.6.3).
 *
 * Job ExpireAiPausesJob detecta conversas com ai_paused_until expirado,
 * zera o campo e emite ConversaRetomadaPelaIA com motivo='timeout'.
 */
class TakeoverExpirationTest extends TestCase
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
        [$this->tenant, $this->attendant] = $this->tenantAndUserForRole('clinica-takeover-expiration', 'atendente');

        $this->channel = Channel::factory()
            ->forTenant($this->tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();
    }

    #[Test]
    public function expire_ai_pauses_job_finds_conversations_with_expired_ai_paused_until(): void
    {
        Event::fake([ConversaRetomadaPelaIA::class]);

        // Expired pause (1 hour ago)
        $expiredConversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->aberta()
            ->create([
                'ai_paused_until' => now()->subHour(),
                'ai_pause_set_by' => $this->attendant->id,
            ]);

        // Active pause (future) — should NOT be touched
        $activeConversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->aberta()
            ->create([
                'ai_paused_until' => now()->addHour(),
                'ai_pause_set_by' => $this->attendant->id,
            ]);

        (new ExpireAiPausesJob)->handle(
            app(ConversaIATogglingContract::class)
        );

        $expiredConversation->refresh();
        $activeConversation->refresh();

        $this->assertNull($expiredConversation->ai_paused_until);
        $this->assertNotNull($activeConversation->ai_paused_until);
    }

    #[Test]
    public function expire_ai_pauses_job_zeroes_ai_paused_until_and_ai_pause_set_by(): void
    {
        Event::fake([ConversaRetomadaPelaIA::class]);

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->aberta()
            ->create([
                'ai_paused_until' => now()->subMinutes(5),
                'ai_pause_set_by' => $this->attendant->id,
            ]);

        (new ExpireAiPausesJob)->handle(
            app(ConversaIATogglingContract::class)
        );

        $conversation->refresh();
        $this->assertNull($conversation->ai_paused_until);
        $this->assertNull($conversation->ai_pause_set_by);
    }

    #[Test]
    public function expire_ai_pauses_job_fires_conversa_retomada_pela_i_a_with_motivo_timeout(): void
    {
        Event::fake([ConversaRetomadaPelaIA::class]);

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->aberta()
            ->create([
                'ai_paused_until' => now()->subMinutes(2),
                'ai_pause_set_by' => $this->attendant->id,
            ]);

        (new ExpireAiPausesJob)->handle(
            app(ConversaIATogglingContract::class)
        );

        Event::assertDispatched(ConversaRetomadaPelaIA::class, function (ConversaRetomadaPelaIA $event) use ($conversation): bool {
            return $event->conversation->id === $conversation->id
                && $event->mode === 'timeout';
        });
    }

    #[Test]
    public function expire_ai_pauses_job_runs_within_60_seconds_window_from_actual_expiration(): void
    {
        Event::fake([ConversaRetomadaPelaIA::class]);

        // Pause expired exactly 59 seconds ago — must be caught by job
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->aberta()
            ->create([
                'ai_paused_until' => now()->subSeconds(59),
                'ai_pause_set_by' => $this->attendant->id,
            ]);

        (new ExpireAiPausesJob)->handle(
            app(ConversaIATogglingContract::class)
        );

        $conversation->refresh();
        $this->assertNull($conversation->ai_paused_until);
    }

    #[Test]
    public function expire_ai_pauses_job_is_idempotent_does_not_duplicate_events(): void
    {
        Event::fake([ConversaRetomadaPelaIA::class]);

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->aberta()
            ->create([
                'ai_paused_until' => now()->subMinutes(5),
                'ai_pause_set_by' => $this->attendant->id,
            ]);

        $contract = app(ConversaIATogglingContract::class);

        // Run twice
        (new ExpireAiPausesJob)->handle($contract);
        (new ExpireAiPausesJob)->handle($contract);

        // Event should only fire once (first run) — second run finds no expired conversations
        Event::assertDispatchedTimes(ConversaRetomadaPelaIA::class, 1);
    }
}
