<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\Tools;

use App\Domain\Ai\Tools\CreateOrFindLeadTool;
use App\Domain\Ai\Tools\HoldSlotTool;
use App\Domain\Ai\Tools\Support\ToolContext;
use App\Domain\Ai\Tools\Support\ToolInvocationLogger;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Agenda\AppointmentType;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use App\Services\Agenda\SlotReservationService;
use App\Services\Pacientes\PacienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Ai\Tools\Request;
use Tests\Support\AiConversationFactory;
use Tests\TestCase;

/**
 * Feature 017 (US5) — T043/T044: ferramentas de ESCRITA reversível
 * (create-or-find-lead, hold-slot). Nunca confirmam agendamento/pagamento.
 */
final class WriteToolsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->app->instance('tenant', $this->tenant);
        $this->conversation = AiConversationFactory::conversation($this->tenant);
    }

    private function context(?int $patientId = null, ?string $phone = null): ToolContext
    {
        return new ToolContext($this->tenant->id, $this->conversation->id, $patientId, $phone, 'corr-1');
    }

    private function logger(): ToolInvocationLogger
    {
        return app(ToolInvocationLogger::class);
    }

    public function test_creates_lead_when_contact_is_new(): void
    {
        $result = (new CreateOrFindLeadTool($this->context(phone: '+5579999990000'), $this->logger(), app(PacienteService::class)))
            ->handle(new Request([]));

        $this->assertStringContainsString('Novo lead', $result);
        $this->assertDatabaseHas('pacientes', [
            'tenant_id' => $this->tenant->id,
            'status' => 'lead',
            'origem' => 'whatsapp',
            'telefone_primario_normalizado' => '5579999990000',
        ]);
    }

    public function test_finds_existing_lead_by_phone(): void
    {
        Paciente::factory()->create([
            'tenant_id' => $this->tenant->id,
            'telefone_primario' => '+5579999990000',
            'status' => 'lead',
        ]);

        $result = (new CreateOrFindLeadTool($this->context(phone: '+5579999990000'), $this->logger(), app(PacienteService::class)))
            ->handle(new Request([]));

        $this->assertStringContainsString('já registrado', $result);
        $this->assertDatabaseCount('pacientes', 1);
    }

    public function test_hold_slot_creates_ia_reservation(): void
    {
        $professional = Professional::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        $type = AppointmentType::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        $startsAt = Carbon::now()->addDays(2)->setTime(15, 0);

        $result = (new HoldSlotTool($this->context(), $this->logger(), app(SlotReservationService::class)))
            ->handle(new Request([
                'professional_id' => $professional->id,
                'appointment_type_id' => $type->id,
                'starts_at' => $startsAt->toIso8601String(),
            ]));

        $this->assertStringContainsString('reservado provisoriamente', $result);
        $this->assertDatabaseHas('slot_reservations', [
            'tenant_id' => $this->tenant->id,
            'professional_id' => $professional->id,
            'holder_type' => 'ia',
            'holder_id' => (string) $this->conversation->id,
        ]);
        // Não confirma agendamento (handoff).
        $this->assertDatabaseCount('appointments', 0);
    }

    // Conflito de horário (SlotConflictException → resposta graciosa) é testado em
    // HoldSlotConflictTest (usa DatabaseMigrations: o recovery SELECT do serviço
    // após o 23505 não funciona sob a transação do RefreshDatabase).
}
