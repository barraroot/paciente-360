<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Assignment\Models\AiConversationAssignment;
use App\Domain\Ai\Matrix\Models\AiPersonaChannel;
use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Messaging\Conversation\Services\HumanTakeoverService;
use App\Jobs\Ai\ProcessAiResponseJob;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\Support\AiConversationFactory;
use Tests\TestCase;

/**
 * Listener ReprocessLastInboundOnAiResume — ao retomar a IA (manual OR timeout),
 * reprocessa a conversa se houver inbound do paciente pendente de resposta.
 */
final class ReprocessLastInboundOnAiResumeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private AiPersona $persona;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->app->instance('tenant', $this->tenant);

        $model = AiModel::factory()->create();
        $this->persona = AiPersona::factory()->forTenant($this->tenant)->create(['ai_model_id' => $model->id]);
        AiPersonaChannel::create([
            'tenant_id' => $this->tenant->id,
            'ai_persona_id' => $this->persona->id,
            'channel_type' => 'whatsapp',
            'is_active' => true,
        ]);
    }

    public function test_resume_dispatches_when_last_message_is_pending_inbound(): void
    {
        Queue::fake();

        $conversation = AiConversationFactory::conversation($this->tenant);
        $conversation->update(['ai_paused_until' => now()->addHour()]);

        AiConversationFactory::seedTurns($this->tenant, $conversation, [
            ['role' => 'ai', 'body' => 'Você prefere online ou presencial?'],
            ['role' => 'patient', 'body' => 'presencial, depois das 18h'],
        ]);

        app(HumanTakeoverService::class)->resumeAi($conversation->refresh(), 'manual');

        Queue::assertPushed(ProcessAiResponseJob::class, function (ProcessAiResponseJob $job) use ($conversation): bool {
            return $job->conversationId === $conversation->id
                && $job->tenantId === $this->tenant->id;
        });
    }

    public function test_resume_via_timeout_also_dispatches(): void
    {
        Queue::fake();

        $conversation = AiConversationFactory::conversation($this->tenant);
        $conversation->update(['ai_paused_until' => now()->addHour()]);

        AiConversationFactory::seedTurns($this->tenant, $conversation, [
            ['role' => 'patient', 'body' => 'oi, quero marcar consulta'],
        ]);

        // Simula o caller do ExpireAiPausesJob.
        app(HumanTakeoverService::class)->resumeAi($conversation->refresh(), 'timeout');

        Queue::assertPushed(ProcessAiResponseJob::class, 1);
    }

    public function test_resume_does_not_dispatch_when_last_message_is_outbound_ai(): void
    {
        Queue::fake();

        $conversation = AiConversationFactory::conversation($this->tenant);
        $conversation->update(['ai_paused_until' => now()->addHour()]);

        AiConversationFactory::seedTurns($this->tenant, $conversation, [
            ['role' => 'patient', 'body' => 'oi'],
            ['role' => 'ai', 'body' => 'Olá! Como posso ajudar?'],
        ]);

        app(HumanTakeoverService::class)->resumeAi($conversation->refresh(), 'manual');

        Queue::assertNotPushed(ProcessAiResponseJob::class);
    }

    public function test_resume_does_not_dispatch_when_last_message_is_outbound_human(): void
    {
        Queue::fake();

        $conversation = AiConversationFactory::conversation($this->tenant);
        $conversation->update(['ai_paused_until' => now()->addHour()]);

        AiConversationFactory::seedTurns($this->tenant, $conversation, [
            ['role' => 'patient', 'body' => 'oi'],
            ['role' => 'user', 'body' => 'Oi, sou a Maria da clínica, posso te ajudar.'],
        ]);

        app(HumanTakeoverService::class)->resumeAi($conversation->refresh(), 'manual');

        Queue::assertNotPushed(ProcessAiResponseJob::class);
    }

    public function test_resume_does_not_dispatch_when_channel_has_no_active_persona(): void
    {
        // Desativa a única persona do canal montada no setUp.
        AiPersonaChannel::query()->update(['is_active' => false]);

        Queue::fake();

        $conversation = AiConversationFactory::conversation($this->tenant);
        $conversation->update(['ai_paused_until' => now()->addHour()]);

        AiConversationFactory::seedTurns($this->tenant, $conversation, [
            ['role' => 'patient', 'body' => 'oi'],
        ]);

        app(HumanTakeoverService::class)->resumeAi($conversation->refresh(), 'manual');

        Queue::assertNotPushed(ProcessAiResponseJob::class);
    }

    public function test_resume_does_not_duplicate_when_debounce_already_held(): void
    {
        Queue::fake();

        $conversation = AiConversationFactory::conversation($this->tenant);
        $conversation->update(['ai_paused_until' => now()->addHour()]);

        AiConversationFactory::seedTurns($this->tenant, $conversation, [
            ['role' => 'patient', 'body' => 'oi'],
        ]);

        // Simula que o trigger inbound já agendou um job nesta janela.
        Cache::put("ai:debounce:{$conversation->id}", true, 8);

        app(HumanTakeoverService::class)->resumeAi($conversation->refresh(), 'manual');

        Queue::assertNotPushed(ProcessAiResponseJob::class);
    }

    public function test_resume_is_idempotent_when_already_unpaused(): void
    {
        Queue::fake();

        $conversation = AiConversationFactory::conversation($this->tenant);
        // ai_paused_until já é null (default da factory) → resumeAi retorna early
        // sem emitir evento → listener nem roda.

        AiConversationFactory::seedTurns($this->tenant, $conversation, [
            ['role' => 'patient', 'body' => 'oi'],
        ]);

        app(HumanTakeoverService::class)->resumeAi($conversation->refresh(), 'manual');

        Queue::assertNotPushed(ProcessAiResponseJob::class);
    }

    public function test_resume_also_reactivates_paused_assignment(): void
    {
        Queue::fake();

        $conversation = AiConversationFactory::conversation($this->tenant);
        $conversation->update(['ai_paused_until' => now()->addHour()]);

        // Simula o estado pós-escalação: assignment paused, conversa pausada.
        AiConversationAssignment::create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $conversation->id,
            'channel_type' => 'whatsapp',
            'ai_persona_id' => $this->persona->id,
            'status' => AiConversationAssignment::STATUS_PAUSED,
            'assigned_at' => now()->subMinutes(10),
            'paused_at' => now()->subMinutes(2),
            'metadata' => [],
        ]);

        AiConversationFactory::seedTurns($this->tenant, $conversation, [
            ['role' => 'patient', 'body' => 'oi'],
        ]);

        app(HumanTakeoverService::class)->resumeAi($conversation->refresh(), 'manual');

        $assignment = AiConversationAssignment::where('conversation_id', $conversation->id)->first();
        $this->assertSame(AiConversationAssignment::STATUS_ASSIGNED, $assignment->status);
        $this->assertNull($assignment->paused_at);
    }

    public function test_resume_leaves_already_active_assignment_untouched(): void
    {
        $conversation = AiConversationFactory::conversation($this->tenant);
        $conversation->update(['ai_paused_until' => now()->addHour()]);

        $assignment = AiConversationAssignment::create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $conversation->id,
            'channel_type' => 'whatsapp',
            'ai_persona_id' => $this->persona->id,
            'status' => AiConversationAssignment::STATUS_ASSIGNED,
            'assigned_at' => now()->subMinutes(10),
            'metadata' => [],
        ]);
        $originalUpdatedAt = $assignment->updated_at;

        app(HumanTakeoverService::class)->resumeAi($conversation->refresh(), 'manual');

        $assignment->refresh();
        $this->assertSame(AiConversationAssignment::STATUS_ASSIGNED, $assignment->status);
        $this->assertTrue($originalUpdatedAt->equalTo($assignment->updated_at), 'idempotent — no write when already active');
    }
}
