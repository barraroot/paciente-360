<?php

namespace Tests\Feature\Fase3\US6_Takeover;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Events\ConversaRetomadaPelaIA;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T167 — Feature tests para Release To AI Manual (AC-4.6.4).
 *
 * POST /inbox/conversations/{id}/release-to-ai
 * Zera ai_paused_until e emite ConversaRetomadaPelaIA com motivo='manual'.
 */
class ReleaseToAiTest extends TestCase
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
        [$this->tenant, $this->attendant] = $this->tenantAndUserForRole('clinica-release-ai', 'atendente');

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
    public function release_endpoint_zeroes_ai_paused_until_immediately(): void
    {
        Event::fake([ConversaRetomadaPelaIA::class]);

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->aberta()
            ->create([
                'ai_paused_until' => now()->addHour(),
                'ai_pause_set_by' => $this->attendant->id,
            ]);

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/release-to-ai")
        );

        $response->assertStatus(200);

        $conversation->refresh();
        $this->assertNull($conversation->ai_paused_until);
        $this->assertNull($conversation->ai_pause_set_by);
    }

    #[Test]
    public function release_fires_conversa_retomada_pela_i_a_with_motivo_manual(): void
    {
        Event::fake([ConversaRetomadaPelaIA::class]);

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->aberta()
            ->create([
                'ai_paused_until' => now()->addHour(),
                'ai_pause_set_by' => $this->attendant->id,
            ]);

        $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/release-to-ai")
        )->assertStatus(200);

        Event::assertDispatched(ConversaRetomadaPelaIA::class, function (ConversaRetomadaPelaIA $event) use ($conversation): bool {
            return $event->conversation->id === $conversation->id
                && $event->mode === 'manual';
        });
    }

    #[Test]
    public function release_on_already_unpaused_conversation_returns_200_no_op(): void
    {
        Event::fake([ConversaRetomadaPelaIA::class]);

        // Conversation with no active pause
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->aberta()
            ->create([
                'ai_paused_until' => null,
                'ai_pause_set_by' => null,
            ]);

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/release-to-ai")
        );

        // Idempotent — returns 200 but does nothing meaningful
        $response->assertStatus(200);

        $conversation->refresh();
        $this->assertNull($conversation->ai_paused_until);
    }

    #[Test]
    public function requires_inbox_takeover_ai_ability(): void
    {
        $financeiro = $this->userForRole($this->tenant, 'financeiro');
        Sanctum::actingAs($financeiro, ['*']);

        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->aberta()
            ->create([
                'ai_paused_until' => now()->addHour(),
                'ai_pause_set_by' => $this->attendant->id,
            ]);

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/release-to-ai")
        );

        $response->assertStatus(403);
    }
}
