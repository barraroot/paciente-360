<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Renewal\PrescriptionRenewal;
use App\Models\Paciente;
use Carbon\Carbon;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T126 — AC-8.3.6: IA não pode renovar receita cancelada ou já renovada.
 *
 * Retorna 422 com `prescription_not_eligible_for_renewal`.
 */
class PrescriptionRenewalStateGuardTest extends TestCase
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
     * AC-8.3.6 — Receita cancelada retorna 422.
     */
    public function test_renew_cancelled_prescription_returns_422(): void
    {
        Event::fake();

        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-guard-cancel', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $prescription = Prescription::factory()
            ->cancelled()
            ->create([
                'tenant_id' => $tenant->id,
                'patient_id' => $paciente->id,
                'professional_id' => $medico->id,
                'issued_at' => Carbon::today()->subDays(20),
                'expires_at' => Carbon::today()->addDays(10),
            ]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/renew"),
            ['initiated_by' => 'ai'],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertStatus(422);
        $this->assertSame(
            'prescription_not_eligible_for_renewal',
            $response->json('error'),
        );
    }

    /**
     * AC-8.3.6 — Receita superseded (já renovada) retorna 422.
     */
    public function test_renew_superseded_prescription_returns_422(): void
    {
        Event::fake();

        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-guard-superseded', 'medico');
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
                'status' => 'superseded',
            ]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/renew"),
            ['initiated_by' => 'ai'],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertStatus(422);
        $this->assertSame(
            'prescription_not_eligible_for_renewal',
            $response->json('error'),
        );
    }

    /**
     * AC-8.3.6 — Receita fora da janela D-30 retorna 422.
     */
    public function test_renew_prescription_outside_window_returns_422(): void
    {
        Event::fake();

        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-guard-window', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $paciente = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $prescription = Prescription::factory()
            ->common()
            ->create([
                'tenant_id' => $tenant->id,
                'patient_id' => $paciente->id,
                'professional_id' => $medico->id,
                'issued_at' => Carbon::today(),
                'expires_at' => Carbon::today()->addDays(90), // fora da janela D-30
                'status' => 'active',
            ]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/renew"),
            ['initiated_by' => 'ai'],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertStatus(422);
        $this->assertSame(
            'prescription_not_eligible_for_renewal',
            $response->json('error'),
        );
    }

    /**
     * AC-8.3.6 — Receita já renovada (com renewed_prescription_id setado) retorna 422.
     */
    public function test_renew_already_renewed_prescription_returns_422(): void
    {
        Event::fake();

        [$tenant, $medico] = $this->tenantAndUserForRole('clinica-guard-renewed', 'medico');
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

        $newPrescription = Prescription::factory()
            ->common()
            ->create([
                'tenant_id' => $tenant->id,
                'patient_id' => $paciente->id,
                'professional_id' => $medico->id,
                'issued_at' => Carbon::today(),
                'expires_at' => Carbon::today()->addDays(90),
                'status' => 'active',
                'renewed_from_id' => $prescription->id,
            ]);

        // Criar renewal já completada
        PrescriptionRenewal::factory()->create([
            'tenant_id' => $tenant->id,
            'original_prescription_id' => $prescription->id,
            'renewed_prescription_id' => $newPrescription->id,
            'initiated_by' => 'ai',
        ]);

        $response = $this->postJson(
            $this->tenantUrl($tenant, "/prescriptions/{$prescription->id}/renew"),
            ['initiated_by' => 'ai'],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertStatus(422);
        $this->assertSame(
            'prescription_not_eligible_for_renewal',
            $response->json('error'),
        );
    }
}
