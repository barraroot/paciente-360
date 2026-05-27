<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\Tools;

use App\Domain\Ai\Tools\HoldSlotTool;
use App\Domain\Ai\Tools\Support\ToolContext;
use App\Domain\Ai\Tools\Support\ToolInvocationLogger;
use App\Models\Agenda\AppointmentType;
use App\Models\Agenda\SlotReservation;
use App\Models\Professional;
use App\Models\Tenant;
use App\Services\Agenda\SlotReservationService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Laravel\Ai\Tools\Request;
use Tests\Support\AiConversationFactory;
use Tests\TestCase;

/**
 * Feature 017 (US5, FR-018/030) — conflito de horário com o fluxo REAL.
 *
 * Usa DatabaseMigrations (sem transação envolvente) porque o serviço de reserva,
 * ao tratar o 23505, faz um SELECT de recuperação que não funcionaria sob a
 * transação do RefreshDatabase. Em produção não há transação envolvente.
 */
final class HoldSlotConflictTest extends TestCase
{
    use DatabaseMigrations;

    public function test_hold_slot_returns_graceful_message_on_conflict(): void
    {
        $tenant = Tenant::factory()->create();
        $this->app->instance('tenant', $tenant);
        $conversation = AiConversationFactory::conversation($tenant);

        $professional = Professional::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $type = AppointmentType::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $startsAt = Carbon::now()->addDays(3)->setTime(10, 0);

        // Horário já reservado (ativo) → o próximo hold deve conflitar.
        SlotReservation::factory()->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $professional->id,
            'appointment_type_id' => $type->id,
            'starts_at' => $startsAt,
            'released_at' => null,
        ]);

        $context = new ToolContext($tenant->id, $conversation->id, null, null, 'corr-conflict');

        $result = (new HoldSlotTool($context, app(ToolInvocationLogger::class), app(SlotReservationService::class)))
            ->handle(new Request([
                'professional_id' => $professional->id,
                'appointment_type_id' => $type->id,
                'starts_at' => $startsAt->toIso8601String(),
            ]));

        $this->assertStringContainsString('indisponível', $result);
        // Não criou uma segunda reserva ativa para o mesmo horário.
        $this->assertSame(1, SlotReservation::query()->whereNull('released_at')->where('starts_at', $startsAt)->count());
    }
}
