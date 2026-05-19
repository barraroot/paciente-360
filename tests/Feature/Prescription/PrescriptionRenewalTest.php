<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Renewal\PrescriptionRenewal;
use App\Events\Prescription\RenovacaoSolicitadaPelaIA;
use App\Models\Agenda\Appointment;
use App\Models\Agenda\AppointmentType;
use App\Models\Paciente;
use App\Models\Professional;
use Carbon\Carbon;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T124 — AC-8.3.2: POST /prescriptions/{id}/renew cria row em
 * `prescription_renewals`, vincula appointment_id e emite evento.
 */
class PrescriptionRenewalTest extends TestCase
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
     * AC-8.3.2 — POST /renew cria PrescriptionRenewal e emite RenovacaoSolicitadaPelaIA.
     */
    public function test_renew_creates_prescription_renewal_row_and_emits_event(): void
    {
        Event::fake([RenovacaoSolicitadaPelaIA::class]);

        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-renew-1', 'medico');
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

        // Verifica criação de PrescriptionRenewal
        $this->assertDatabaseHas('prescription_renewals', [
            'original_prescription_id' => $prescription->id,
            'initiated_by' => 'ai',
            'tenant_id' => $tenant->id,
        ]);

        // Verifica evento emitido
        Event::assertDispatched(RenovacaoSolicitadaPelaIA::class, function ($event) use ($prescription) {
            return $event->prescriptionId === $prescription->id
                && $event->patientId === $prescription->patient_id
                && $event->professionalId === $prescription->professional_id;
        });
    }

    /**
     * AC-8.3.2 — appointment_id é vinculado na renewal quando fornecido.
     */
    public function test_renew_links_appointment_id_when_provided(): void
    {
        Event::fake([RenovacaoSolicitadaPelaIA::class]);

        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-renew-2', 'medico');
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

        // Cria professional vinculado ao medico e appointment no mesmo tenant
        $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
        $appointmentType = AppointmentType::factory()->create(['tenant_id' => $tenant->id]);
        $appointment = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $professional->id,
            'paciente_id' => $paciente->id,
            'appointment_type_id' => $appointmentType->id,
        ]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/renew"),
            [
                'initiated_by' => 'ai',
                'appointment_id' => $appointment->id,
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();

        $this->assertDatabaseHas('prescription_renewals', [
            'original_prescription_id' => $prescription->id,
            'initiated_by' => 'ai',
            'appointment_id' => $appointment->id,
        ]);
    }

    /**
     * AC-8.3.2 — Resposta 201 com dados do PrescriptionRenewal.
     */
    public function test_renew_returns_prescription_renewal_resource(): void
    {
        Event::fake([RenovacaoSolicitadaPelaIA::class]);

        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-renew-3', 'medico');
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
        $response->assertJsonStructure([
            'data' => [
                'id',
                'original_prescription_id',
                'renewed_prescription_id',
                'initiated_by',
                'appointment_id',
                'created_at',
            ],
        ]);
    }
}
