<?php

namespace Tests\Feature\Prescription;

use App\Events\Prescription\PrescricaoCriada;
use App\Models\Paciente;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T033 — AC-8.1.1, AC-8.1.2, AC-8.1.3
 *
 * Validade controlada/especial = 30d fixos.
 * Receita comum aceita apenas {30,60,90,180}.
 */
class PrescriptionCreationTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // -----------------------------------------------------------------------
    // AC-8.1.1 — Médico cria receita comum com sucesso
    // -----------------------------------------------------------------------

    public function test_medico_creates_common_prescription_successfully(): void
    {
        Event::fake([PrescricaoCriada::class]);

        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-presc-create', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $payload = [
            'patient_id' => $paciente->id,
            'type' => 'common',
            'issued_at' => Carbon::today()->toDateString(),
            'duration_days' => 30,
            'items' => [
                ['medication_name' => 'Losartana 50mg', 'posology' => '1 comp por dia'],
            ],
        ];

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            $payload,
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.type', 'common');
        $response->assertJsonPath('data.status', 'active');
        $response->assertJsonPath('data.professional_id', $medico->id);

        $this->assertDatabaseHas('prescriptions', [
            'tenant_id' => $tenant->id,
            'patient_id' => $paciente->id,
            'professional_id' => $medico->id,
            'type' => 'common',
            'status' => 'active',
        ]);

        Event::assertDispatched(PrescricaoCriada::class);
    }

    public function test_prescription_links_professional_id_to_authenticated_user(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-presc-prof', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'common',
                'issued_at' => Carbon::today()->toDateString(),
                'duration_days' => 60,
                'items' => [
                    ['medication_name' => 'Metformina', 'posology' => '1 comp após refeição'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.professional_id', $medico->id);
    }

    // -----------------------------------------------------------------------
    // AC-8.1.2 — Receita especial e controlada: validade bloqueada em 30d
    // -----------------------------------------------------------------------

    public function test_special_prescription_forces_30_days_validity(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-presc-special', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'special',
                'issued_at' => Carbon::today()->toDateString(),
                'items' => [
                    ['medication_name' => 'Clonazepam 0.5mg', 'posology' => '1 comp ao dormir'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();

        $expiresAt = Carbon::today()->addDays(30)->toDateString();
        $response->assertJsonPath('data.expires_at', $expiresAt);
    }

    public function test_controlled_prescription_forces_30_days_validity(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-presc-controlled', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'controlled',
                'issued_at' => Carbon::today()->toDateString(),
                'items' => [
                    ['medication_name' => 'Ritalina 10mg', 'posology' => '1 comp pela manhã'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();

        $expiresAt = Carbon::today()->addDays(30)->toDateString();
        $response->assertJsonPath('data.expires_at', $expiresAt);
    }

    public function test_attempt_to_override_expires_at_for_special_is_rejected(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-presc-override', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        // Attempt to pass expires_at directly — must be ignored (not included in rules)
        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'special',
                'issued_at' => Carbon::today()->toDateString(),
                'expires_at' => Carbon::today()->addDays(90)->toDateString(), // override attempt
                'items' => [
                    ['medication_name' => 'Midazolam', 'posology' => '1 comp'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        // Should succeed (expires_at is server-side computed, override ignored)
        // but the stored expires_at must be today+30, not today+90
        if ($response->status() === 201) {
            $response->assertJsonPath('data.expires_at', Carbon::today()->addDays(30)->toDateString());
        } else {
            $response->assertStatus(422);
        }
    }

    // -----------------------------------------------------------------------
    // AC-8.1.3 — Receita comum: duration_days deve ser {30,60,90,180}
    // -----------------------------------------------------------------------

    public function test_common_prescription_accepts_valid_duration_presets(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-presc-dur', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        foreach ([30, 60, 90, 180] as $days) {
            $response = $this->postJson(
                $this->tenantUrl($tenant, '/prescriptions'),
                [
                    'patient_id' => $paciente->id,
                    'type' => 'common',
                    'issued_at' => Carbon::today()->toDateString(),
                    'duration_days' => $days,
                    'items' => [
                        ['medication_name' => 'Paracetamol', 'posology' => '1 comp 8/8h'],
                    ],
                ],
                ['X-Tenant-Slug' => $tenant->slug],
            );

            $response->assertCreated("Expected 201 for duration_days={$days}");
        }
    }

    public function test_common_prescription_rejects_invalid_duration(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-presc-inv-dur', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'common',
                'issued_at' => Carbon::today()->toDateString(),
                'duration_days' => 45, // not in {30,60,90,180}
                'items' => [
                    ['medication_name' => 'Ibuprofeno', 'posology' => '1 comp 8/8h'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['duration_days']);
    }

    public function test_common_prescription_requires_duration_days(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-presc-no-dur', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'common',
                'issued_at' => Carbon::today()->toDateString(),
                // missing duration_days
                'items' => [
                    ['medication_name' => 'Vitamina C', 'posology' => '1 cp por dia'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['duration_days']);
    }

    public function test_prescription_requires_at_least_one_item(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-presc-no-items', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'common',
                'issued_at' => Carbon::today()->toDateString(),
                'duration_days' => 30,
                'items' => [],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['items']);
    }

    public function test_prescription_rejects_patient_from_other_tenant(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-presc-patient-a', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $otherTenant = $this->bootstrapTenantWithRoles('other-tenant-patient');
        $foreignPatient = Paciente::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $foreignPatient->id,
                'type' => 'common',
                'issued_at' => Carbon::today()->toDateString(),
                'duration_days' => 30,
                'items' => [
                    ['medication_name' => 'Dipirona', 'posology' => '1 comp 6/6h'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertUnprocessable();
    }

    public function test_prescription_stores_items(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-presc-items-test', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'common',
                'issued_at' => Carbon::today()->toDateString(),
                'duration_days' => 30,
                'items' => [
                    [
                        'medication_name' => 'Atenolol 25mg',
                        'posology' => '1 comp por dia',
                        'concentration' => '25mg',
                        'pharmaceutical_form' => 'comprimido',
                    ],
                    [
                        'medication_name' => 'Hidroclorotiazida 25mg',
                        'posology' => '1 comp pela manhã',
                    ],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();

        $prescriptionId = $response->json('data.id');
        $this->assertDatabaseHas('prescription_items', [
            'prescription_id' => $prescriptionId,
            'medication_name' => 'Atenolol 25mg',
        ]);
        $this->assertDatabaseHas('prescription_items', [
            'prescription_id' => $prescriptionId,
            'medication_name' => 'Hidroclorotiazida 25mg',
        ]);
    }

    public function test_prescription_records_audit_log_on_creation(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-presc-audit-create', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $this->postJson(
            $this->tenantUrl($tenant, '/prescriptions'),
            [
                'patient_id' => $paciente->id,
                'type' => 'common',
                'issued_at' => Carbon::today()->toDateString(),
                'duration_days' => 30,
                'items' => [
                    ['medication_name' => 'Sinvastatina', 'posology' => '1 comp à noite'],
                ],
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'prescription.created',
        ]);
    }
}
