<?php

namespace Tests\Unit\Messaging;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Contracts\ConversaIATogglingContract;
use App\Domain\Messaging\Conversation\Events\ConversaAssumidaPorHumano;
use App\Domain\Messaging\Conversation\Events\ConversaRetomadaPelaIA;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Conversation\Services\HumanTakeoverService;
use App\Events\Contracts\Auditable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T168 — GATE CONSTITUCIONAL Princípio III.
 *
 * Congela o contrato ConversaIATogglingContract para consumo pela Fase 4 (IA matricial).
 * Este teste DEVE falhar se qualquer mudança de assinatura/payload for feita sem
 * amendment explícito da constitution.
 *
 * NÃO ALTERE ESTE TESTE sem amendment da constitution v1.x.
 */
class ConversaIATogglingContractTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    #[Test]
    public function contract_interface_exists_with_4_required_methods(): void
    {
        $this->assertTrue(
            interface_exists(ConversaIATogglingContract::class),
            'Interface ConversaIATogglingContract must exist'
        );

        $reflection = new \ReflectionClass(ConversaIATogglingContract::class);

        // pauseAi(Conversation, int $minutes, ?User $byUser, string $reason): Conversation
        $this->assertTrue($reflection->hasMethod('pauseAi'), 'pauseAi method must exist');
        $pauseAi = new ReflectionMethod(ConversaIATogglingContract::class, 'pauseAi');
        $params = $pauseAi->getParameters();
        $this->assertCount(4, $params, 'pauseAi must have exactly 4 parameters');
        $this->assertSame('conv', $params[0]->getName());
        $this->assertSame('minutes', $params[1]->getName());
        $this->assertSame('byUser', $params[2]->getName());
        $this->assertSame('reason', $params[3]->getName());
        $pauseReturn = $pauseAi->getReturnType();
        $this->assertNotNull($pauseReturn);
        $this->assertSame(Conversation::class, (string) $pauseReturn);

        // resumeAi(Conversation, string $mode = 'manual'): Conversation
        $this->assertTrue($reflection->hasMethod('resumeAi'), 'resumeAi method must exist');
        $resumeAi = new ReflectionMethod(ConversaIATogglingContract::class, 'resumeAi');
        $resumeParams = $resumeAi->getParameters();
        $this->assertCount(2, $resumeParams, 'resumeAi must have exactly 2 parameters');
        $this->assertSame('conv', $resumeParams[0]->getName());
        $this->assertSame('mode', $resumeParams[1]->getName());
        $this->assertTrue($resumeParams[1]->isOptional(), 'mode param must have default value');
        $this->assertSame('manual', $resumeParams[1]->getDefaultValue());
        $resumeReturn = $resumeAi->getReturnType();
        $this->assertSame(Conversation::class, (string) $resumeReturn);

        // isPaused(Conversation): bool
        $this->assertTrue($reflection->hasMethod('isPaused'), 'isPaused method must exist');
        $isPaused = new ReflectionMethod(ConversaIATogglingContract::class, 'isPaused');
        $this->assertSame('bool', (string) $isPaused->getReturnType());

        // secondsUntilResume(Conversation): ?int
        $this->assertTrue($reflection->hasMethod('secondsUntilResume'), 'secondsUntilResume method must exist');
        $secondsMethod = new ReflectionMethod(ConversaIATogglingContract::class, 'secondsUntilResume');
        $returnType = $secondsMethod->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertTrue($returnType->allowsNull(), 'secondsUntilResume must return ?int');
    }

    #[Test]
    public function human_takeover_service_implements_conversa_ia_toggling_contract(): void
    {
        $this->assertTrue(
            class_exists(HumanTakeoverService::class),
            'HumanTakeoverService class must exist'
        );

        $this->assertInstanceOf(
            ConversaIATogglingContract::class,
            app(ConversaIATogglingContract::class)
        );

        $concrete = app(ConversaIATogglingContract::class);
        $this->assertInstanceOf(HumanTakeoverService::class, $concrete);
    }

    #[Test]
    public function conversa_assumida_por_humano_event_has_stable_payload_schema(): void
    {
        $this->seedRoles();
        [$tenant, $user] = $this->tenantAndUserForRole('clinica-contract-test', 'atendente');

        $channel = Channel::factory()
            ->forTenant($tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();

        $conversation = Conversation::factory()
            ->forTenant($tenant)
            ->for($channel, 'channel')
            ->aberta()
            ->create();

        $event = new ConversaAssumidaPorHumano(
            conversation: $conversation,
            executorId: $user->id,
            reason: 'manual_click',
            pauseDurationSeconds: 1800,
        );

        $payload = $event->auditPayload();

        // Frozen schema — Fase 4 depends on these exact keys
        $this->assertArrayHasKey('conversation_id', $payload);
        $this->assertArrayHasKey('executor_id', $payload);
        $this->assertArrayHasKey('motivo', $payload);
        $this->assertArrayHasKey('duracao_pausa_seg', $payload);

        $this->assertSame($conversation->id, $payload['conversation_id']);
        $this->assertSame($user->id, $payload['executor_id']);
        $this->assertSame('manual_click', $payload['motivo']);
        $this->assertSame(1800, $payload['duracao_pausa_seg']);

        // Validate motivo enum values
        $validMotivos = ['manual_click', 'mensagem_enviada', 'reprise'];
        $this->assertContains($payload['motivo'], $validMotivos);
    }

    #[Test]
    public function conversa_retomada_pela_i_a_event_has_stable_payload_schema(): void
    {
        $this->seedRoles();
        [$tenant] = $this->tenantAndUserForRole('clinica-contract-test-2', 'atendente');

        $channel = Channel::factory()
            ->forTenant($tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();

        $conversation = Conversation::factory()
            ->forTenant($tenant)
            ->for($channel, 'channel')
            ->aberta()
            ->create();

        $event = new ConversaRetomadaPelaIA(
            conversation: $conversation,
            mode: 'timeout',
        );

        $payload = $event->auditPayload();

        // Frozen schema — Fase 4 depends on these exact keys
        $this->assertArrayHasKey('conversation_id', $payload);
        $this->assertArrayHasKey('motivo', $payload);

        $this->assertSame($conversation->id, $payload['conversation_id']);
        $this->assertSame('timeout', $payload['motivo']);

        // Validate motivo enum values
        $validMotivos = ['manual', 'timeout'];
        $this->assertContains($payload['motivo'], $validMotivos);
    }

    #[Test]
    public function events_implement_auditable_interface(): void
    {
        $this->assertContains(
            Auditable::class,
            class_implements(ConversaAssumidaPorHumano::class) ?: []
        );

        $this->assertContains(
            Auditable::class,
            class_implements(ConversaRetomadaPelaIA::class) ?: []
        );
    }

    #[Test]
    public function pause_ai_extends_timer_when_already_paused(): void
    {
        $this->seedRoles();
        [$tenant, $user] = $this->tenantAndUserForRole('clinica-contract-pause-extend', 'atendente');

        $channel = Channel::factory()
            ->forTenant($tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();

        // Conversation already paused for 10 minutes
        $existingPause = now()->addMinutes(10);
        $conversation = Conversation::factory()
            ->forTenant($tenant)
            ->for($channel, 'channel')
            ->aberta()
            ->create([
                'ai_paused_until' => $existingPause,
                'ai_pause_set_by' => $user->id,
            ]);

        $service = app(ConversaIATogglingContract::class);
        $result = $service->pauseAi($conversation, 30, $user, 'reprise');

        $result->refresh();

        // Must be ~30 min from now, not 40 (accumulated)
        $expectedAt = now()->addMinutes(30);
        $diffSeconds = abs($result->ai_paused_until->diffInSeconds($expectedAt));
        $this->assertLessThan(60, $diffSeconds);
    }

    #[Test]
    public function resume_ai_is_idempotent_when_already_unpaused(): void
    {
        $this->seedRoles();
        [$tenant] = $this->tenantAndUserForRole('clinica-contract-idempotent', 'atendente');

        $channel = Channel::factory()
            ->forTenant($tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();

        $conversation = Conversation::factory()
            ->forTenant($tenant)
            ->for($channel, 'channel')
            ->aberta()
            ->create(['ai_paused_until' => null, 'ai_pause_set_by' => null]);

        $service = app(ConversaIATogglingContract::class);

        // Calling resumeAi on already-unpaused conversation must not throw
        $result = $service->resumeAi($conversation, 'manual');

        $result->refresh();
        $this->assertNull($result->ai_paused_until);
        $this->assertNull($result->ai_pause_set_by);
    }

    #[Test]
    public function pause_duration_clamped_to_5_to_240_range(): void
    {
        $this->seedRoles();
        [$tenant, $user] = $this->tenantAndUserForRole('clinica-contract-clamp', 'atendente');

        $channel = Channel::factory()
            ->forTenant($tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();

        $conversation = Conversation::factory()
            ->forTenant($tenant)
            ->for($channel, 'channel')
            ->aberta()
            ->create(['ai_paused_until' => null]);

        $service = app(ConversaIATogglingContract::class);

        // Below minimum (5 min) → clamped to 5
        $result = $service->pauseAi($conversation, 2, $user, 'manual_click');
        $result->refresh();
        $expectedMin = now()->addMinutes(5);
        $diffMin = abs($result->ai_paused_until->diffInSeconds($expectedMin));
        $this->assertLessThan(60, $diffMin, 'Duration below minimum should be clamped to 5 minutes');

        // Reset
        $conversation->update(['ai_paused_until' => null]);

        // Above maximum (240 min) → clamped to 240
        $result = $service->pauseAi($conversation, 300, $user, 'manual_click');
        $result->refresh();
        $expectedMax = now()->addMinutes(240);
        $diffMax = abs($result->ai_paused_until->diffInSeconds($expectedMax));
        $this->assertLessThan(60, $diffMax, 'Duration above maximum should be clamped to 240 minutes');
    }
}
