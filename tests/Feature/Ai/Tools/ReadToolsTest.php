<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\Tools;

use App\Domain\Ai\Tools\GetAvailabilityTool;
use App\Domain\Ai\Tools\GetClinicInfoTool;
use App\Domain\Ai\Tools\GetCurrentPatientTool;
use App\Domain\Ai\Tools\Support\ToolContext;
use App\Domain\Ai\Tools\Support\ToolInvocationLogger;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Agenda\AppointmentType;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use App\Services\Agenda\SlotGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\Support\AiConversationFactory;
use Tests\TestCase;

/**
 * Feature 017 (US5) — T041/T042: ferramentas de LEITURA (clinic-info,
 * availability, current-patient).
 */
final class ReadToolsTest extends TestCase
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

    public function test_get_clinic_info_returns_live_services_and_prices(): void
    {
        AppointmentType::factory()->create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Consulta Enxaqueca',
            'valor_particular' => 300,
            'is_active' => true,
        ]);

        $result = (new GetClinicInfoTool($this->context(), $this->logger()))->handle(new Request(['topic' => 'all']));

        $this->assertStringContainsString('Consulta Enxaqueca', $result);
        $this->assertStringContainsString('300,00', $result);
        $this->assertDatabaseHas('ai_tool_invocations', [
            'tenant_id' => $this->tenant->id,
            'tool_name' => 'get-clinic-info',
            'outcome' => 'success',
        ]);
    }

    public function test_get_availability_returns_string_without_error(): void
    {
        Professional::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        AppointmentType::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);

        $result = (new GetAvailabilityTool($this->context(), $this->logger(), app(SlotGeneratorService::class)))
            ->handle(new Request([]));

        $this->assertIsString($result);
        $this->assertDatabaseHas('ai_tool_invocations', ['tool_name' => 'get-availability']);
    }

    public function test_get_current_patient_is_non_identifying_and_consent_aware(): void
    {
        $patient = Paciente::factory()->create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Mariana Secreta',
            'status' => 'lead',
        ]);

        $result = (new GetCurrentPatientTool($this->context(patientId: $patient->id), $this->logger()))->handle(new Request([]));

        $this->assertStringContainsString('lead', $result);
        // Nunca devolve o nome real ao modelo (placeholder cuida da saudação).
        $this->assertStringNotContainsString('Mariana', $result);
    }

    public function test_get_current_patient_unknown_contact(): void
    {
        $result = (new GetCurrentPatientTool($this->context(phone: '+5511900000000'), $this->logger()))->handle(new Request([]));

        $this->assertStringContainsString('não cadastrado', $result);
    }
}
