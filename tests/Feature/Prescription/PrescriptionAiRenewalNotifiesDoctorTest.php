<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Events\Prescription\RenovacaoSolicitadaPelaIA;
use App\Listeners\Prescription\EnqueueInboxTaskOnAiRenewal;
use App\Models\Paciente;
use Carbon\Carbon;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T125 — AC-8.3.7 / Q13: Listener `EnqueueInboxTaskOnAiRenewal`
 * é acionado quando RenovacaoSolicitadaPelaIA é despachada.
 *
 * Implementação MVP: Log::info com payload completo (DEFERRED real Inbox Fase 3).
 */
class PrescriptionAiRenewalNotifiesDoctorTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * AC-8.3.7 — Evento RenovacaoSolicitadaPelaIA é despachado ao renovar.
     *
     * O teste valida que o evento é emitido com os dados corretos.
     * A integração com Inbox é DEFERRED (MVP usa Log::info).
     */
    public function test_ai_renewal_dispatches_renovacao_solicitada_pela_ia_event(): void
    {
        Event::fake([RenovacaoSolicitadaPelaIA::class]);

        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-notify-1', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $prescription = Prescription::factory()
            ->common()
            ->create([
                'tenant_id' => $tenant->id,
                'patient_id' => $paciente->id,
                'professional_id' => $medico->id,
                'issued_at' => Carbon::today()->subDays(23),
                'expires_at' => Carbon::today()->addDays(7),
                'status' => 'active',
            ]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/renew"),
            ['initiated_by' => 'ai'],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();

        Event::assertDispatched(RenovacaoSolicitadaPelaIA::class, function ($event) use ($prescription, $medico, $paciente) {
            return $event->prescriptionId === $prescription->id
                && $event->professionalId === $medico->id
                && $event->patientId === $paciente->id;
        });
    }

    /**
     * Q13 — Listener EnqueueInboxTaskOnAiRenewal é auto-discovered e registra log.
     *
     * Verifica que o listener existe com tipo-hint correto para auto-discovery.
     */
    public function test_enqueue_inbox_task_listener_is_auto_discoverable(): void
    {
        $listenerClass = EnqueueInboxTaskOnAiRenewal::class;
        $this->assertTrue(class_exists($listenerClass), 'Listener deve existir.');

        $reflection = new \ReflectionClass($listenerClass);
        $handleMethod = $reflection->getMethod('handle');

        $params = $handleMethod->getParameters();
        $this->assertCount(1, $params, 'Listener deve ter exatamente 1 parâmetro.');

        $type = $params[0]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertSame(
            RenovacaoSolicitadaPelaIA::class,
            $type->getName(),
            'Listener handle() deve aceitar RenovacaoSolicitadaPelaIA.',
        );
    }

    /**
     * Q13 — Listener log é registrado com payload correto.
     */
    public function test_listener_logs_inbox_task_request_with_correct_payload(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-notify-log', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $prescription = Prescription::factory()
            ->common()
            ->create([
                'tenant_id' => $tenant->id,
                'patient_id' => $paciente->id,
                'professional_id' => $medico->id,
                'issued_at' => Carbon::today()->subDays(23),
                'expires_at' => Carbon::today()->addDays(7),
                'status' => 'active',
            ]);

        Log::spy();

        $event = new RenovacaoSolicitadaPelaIA(
            prescriptionId: $prescription->id,
            patientId: $prescription->patient_id,
            professionalId: $prescription->professional_id,
            appointmentId: null,
        );

        $listener = app(EnqueueInboxTaskOnAiRenewal::class);
        $listener->handle($event);

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $channel, array $context) use ($prescription): bool {
                return $channel === 'prescription.ai_renewal.inbox_task_requested'
                    && isset($context['prescription_id'])
                    && $context['prescription_id'] === $prescription->id;
            });
    }
}
