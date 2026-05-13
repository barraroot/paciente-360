<?php

namespace Tests\Feature\Fase3\US6_Takeover;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Events\ConversaAssumidaPorHumano;
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
 * T163 — Feature tests para Takeover Manual (AC-4.6.1, AC-4.6.4).
 *
 * POST /inbox/conversations/{id}/takeover seta ai_paused_until e emite evento.
 */
class TakeoverManualTest extends TestCase
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
        [$this->tenant, $this->attendant] = $this->tenantAndUserForRole('clinica-takeover-manual', 'atendente');

        $this->channel = Channel::factory()
            ->forTenant($this->tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    private function makeConversation(): Conversation
    {
        return Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->aberta()
            ->create(['ai_paused_until' => null]);
    }

    #[Test]
    public function takeover_endpoint_sets_ai_paused_until_to_now_plus_default_duration(): void
    {
        Event::fake([ConversaAssumidaPorHumano::class]);
        $conversation = $this->makeConversation();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/takeover"),
            []
        );

        $response->assertStatus(200);

        $conversation->refresh();
        $this->assertNotNull($conversation->ai_paused_until);
        $this->assertTrue($conversation->ai_paused_until->isFuture());

        // Default is 30 minutes; allow ±60 seconds tolerance
        $expectedAt = now()->addMinutes(30);
        $diffSeconds = abs($conversation->ai_paused_until->diffInSeconds($expectedAt));
        $this->assertLessThan(60, $diffSeconds);
    }

    #[Test]
    public function takeover_with_custom_duration_hours_within_1_to_24(): void
    {
        Event::fake([ConversaAssumidaPorHumano::class]);
        $conversation = $this->makeConversation();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/takeover"),
            ['duration_hours' => 4]
        );

        $response->assertStatus(200);

        $conversation->refresh();
        $this->assertNotNull($conversation->ai_paused_until);

        $expectedAt = now()->addHours(4);
        $diffSeconds = abs($conversation->ai_paused_until->diffInSeconds($expectedAt));
        $this->assertLessThan(60, $diffSeconds);
    }

    #[Test]
    public function takeover_with_until_timestamp_explicit(): void
    {
        Event::fake([ConversaAssumidaPorHumano::class]);
        $conversation = $this->makeConversation();

        $until = now()->addHours(6)->toIso8601String();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/takeover"),
            ['until' => $until]
        );

        $response->assertStatus(200);

        $conversation->refresh();
        $this->assertNotNull($conversation->ai_paused_until);
        $diffSeconds = abs($conversation->ai_paused_until->diffInSeconds(now()->addHours(6)));
        $this->assertLessThan(60, $diffSeconds);
    }

    #[Test]
    public function takeover_rejects_duration_hours_outside_range(): void
    {
        $conversation = $this->makeConversation();

        // duration_hours > 24 → 422
        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/takeover"),
            ['duration_hours' => 25]
        );
        $response->assertStatus(422);

        // duration_hours < 1 → 422
        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/takeover"),
            ['duration_hours' => 0]
        );
        $response->assertStatus(422);
    }

    #[Test]
    public function takeover_fires_conversa_assumida_por_humano_with_motivo_manual_click(): void
    {
        Event::fake([ConversaAssumidaPorHumano::class]);
        $conversation = $this->makeConversation();

        $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/takeover"),
            []
        )->assertStatus(200);

        Event::assertDispatched(ConversaAssumidaPorHumano::class, function (ConversaAssumidaPorHumano $event) use ($conversation): bool {
            return $event->conversation->id === $conversation->id
                && $event->reason === 'manual_click';
        });
    }

    #[Test]
    public function takeover_records_executor_id_in_ai_pause_set_by(): void
    {
        Event::fake([ConversaAssumidaPorHumano::class]);
        $conversation = $this->makeConversation();

        $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/takeover"),
            []
        )->assertStatus(200);

        $conversation->refresh();
        $this->assertSame($this->attendant->id, $conversation->ai_pause_set_by);
    }

    #[Test]
    public function requires_inbox_takeover_ai_ability(): void
    {
        // Financeiro role does not have inbox.takeover_ai
        $financeiro = $this->userForRole($this->tenant, 'financeiro');
        Sanctum::actingAs($financeiro, ['*']);

        $conversation = $this->makeConversation();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/takeover"),
            []
        );

        $response->assertStatus(403);
    }

    #[Test]
    public function cross_tenant_conversation_404(): void
    {
        Event::fake([ConversaAssumidaPorHumano::class]);

        // Create a second tenant's conversation
        $otherTenant = $this->bootstrapTenantWithRoles('clinica-other-takeover');
        $otherChannel = Channel::factory()
            ->forTenant($otherTenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();
        $otherConversation = Conversation::factory()
            ->forTenant($otherTenant)
            ->for($otherChannel, 'channel')
            ->aberta()
            ->create();

        // Authenticated as tenant A attendant, trying to access tenant B conversation
        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$otherConversation->id}/takeover"),
            []
        );

        $response->assertStatus(404);
    }
}
