<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionStatus;
use App\Domain\Prescription\Prescription\PrescriptionType;
use App\Models\Paciente;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T150 — AC-8.4.1/3/4/8 — Relatório de receitas.
 *
 * Cobre:
 *  - AC-8.4.1: paginação cursor retorna dados corretos.
 *  - AC-8.4.3: filtros AND combinados (status + type + patient_id).
 *  - AC-8.4.4: definição "renovada" via previous_prescription_id.
 *  - AC-8.4.8: janela default de 30 dias (expires_at entre hoje e hoje+30d).
 *
 * Nota: check constraint `chk_prescription_validity_by_type` exige que
 *   - common: (expires_at - issued_at) IN (30, 60, 90, 180)
 *   - special/controlled: (expires_at - issued_at) = 30
 * Portanto os testes usam issued_at no passado + expires_at no futuro
 * dentro da janela de 30d usando duração válida de 30 dias.
 */
class PrescriptionReportTest extends TestCase
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
     * Cria prescription com constraint válida: issued 20d atrás, expires em +30d = daqui 10d.
     *
     * @return array<string, mixed>
     */
    private function validPrescriptionAttributes(int $tenantId, int $patientId, int $professionalId, int $daysUntilExpiry = 10): array
    {
        // issued_at = hoje - (30 - daysUntilExpiry) para que expires = issued + 30 = hoje + daysUntilExpiry
        $issuedAt = Carbon::today()->subDays(30 - $daysUntilExpiry);

        return [
            'tenant_id' => $tenantId,
            'patient_id' => $patientId,
            'professional_id' => $professionalId,
            'type' => PrescriptionType::Common,
            'status' => PrescriptionStatus::Active,
            'source' => 'manual',
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->addDays(30),
        ];
    }

    public function test_report_index_returns_cursor_paginated_results(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('rpt-cursor', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $patient = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        Prescription::factory()->count(3)->create(
            $this->validPrescriptionAttributes($tenant->id, $patient->id, $medico->id)
        );

        $response = $this->getJson(
            $this->tenantUrl($tenant, '/prescription-reports'),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();

        // Cursor paginator response shape
        $response->assertJsonStructure([
            'data',
            'meta' => ['path', 'per_page', 'next_cursor', 'prev_cursor'],
        ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_report_filters_by_status_and_type_in_and_combination(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('rpt-filter', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $patient = Paciente::factory()->create(['tenant_id' => $tenant->id]);
        $attrs = $this->validPrescriptionAttributes($tenant->id, $patient->id, $medico->id);

        // Active + Common
        Prescription::factory()->create($attrs);

        // Cancelled + Common (should be excluded when filtering active)
        Prescription::factory()->create(array_merge($attrs, [
            'status' => PrescriptionStatus::Cancelled,
            'cancelled_at' => Carbon::now(),
            'cancelled_by_user_id' => $medico->id,
            'cancellation_reason_category' => 'erro_emissao',
            'cancellation_reason' => 'Teste cancelamento',
        ]));

        // Active + Special (should be excluded when filtering common)
        // Special: issued_at = hoje - 20d, expires_at = issued + 30d = hoje + 10d
        $specialIssued = Carbon::today()->subDays(20);
        Prescription::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'professional_id' => $medico->id,
            'type' => PrescriptionType::Special,
            'status' => PrescriptionStatus::Active,
            'source' => 'manual',
            'issued_at' => $specialIssued,
            'expires_at' => $specialIssued->copy()->addDays(30),
        ]);

        $response = $this->getJson(
            $this->tenantUrl($tenant, '/prescription-reports?status=active&type=common'),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('active', $response->json('data.0.status'));
        $this->assertEquals('common', $response->json('data.0.type'));
    }

    public function test_report_filters_by_patient_id(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('rpt-patient', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $patient1 = Paciente::factory()->create(['tenant_id' => $tenant->id]);
        $patient2 = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        Prescription::factory()->create(
            $this->validPrescriptionAttributes($tenant->id, $patient1->id, $medico->id)
        );

        Prescription::factory()->create(
            $this->validPrescriptionAttributes($tenant->id, $patient2->id, $medico->id)
        );

        $response = $this->getJson(
            $this->tenantUrl($tenant, "/prescription-reports?patient_id={$patient1->id}"),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($patient1->id, $response->json('data.0.patient_id'));
    }

    public function test_report_definition_of_renewed_is_via_previous_prescription_id(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('rpt-renewed', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $patient = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        $original = Prescription::factory()->create(
            $this->validPrescriptionAttributes($tenant->id, $patient->id, $medico->id, 10)
        );

        // renewed: issued 25d atrás, expires = issued + 30d = daqui 5d (dentro da janela)
        $renewedIssued = Carbon::today()->subDays(25);
        $renewed = Prescription::factory()->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $medico->id,
            'patient_id' => $patient->id,
            'type' => PrescriptionType::Common,
            'status' => PrescriptionStatus::Active,
            'source' => 'manual',
            'issued_at' => $renewedIssued,
            'expires_at' => $renewedIssued->copy()->addDays(30),
            'renewed_from_id' => $original->id,
        ]);

        $response = $this->getJson(
            $this->tenantUrl($tenant, '/prescription-reports'),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();

        $data = collect($response->json('data'));

        $renewedRow = $data->firstWhere('id', $renewed->id);
        $this->assertNotNull($renewedRow);
        $this->assertEquals($original->id, $renewedRow['renewed_from_id']);
        $this->assertTrue($renewedRow['is_renewed']);

        $originalRow = $data->firstWhere('id', $original->id);
        $this->assertNotNull($originalRow);
        $this->assertFalse($originalRow['is_renewed']);
    }

    public function test_report_default_window_is_30_days_from_today(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('rpt-window', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $patient = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        // Inside window: expires daqui +10d (issued = hoje-20d, duration=30d)
        $inside = Prescription::factory()->create(
            $this->validPrescriptionAttributes($tenant->id, $patient->id, $medico->id, 10)
        );

        // Boundary: expires exatamente em +30d (issued = hoje, duration=30d)
        $boundary = Prescription::factory()->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $medico->id,
            'patient_id' => $patient->id,
            'type' => PrescriptionType::Common,
            'status' => PrescriptionStatus::Active,
            'source' => 'manual',
            'issued_at' => Carbon::today(),
            'expires_at' => Carbon::today()->addDays(30),
        ]);

        // Outside window: expires em +60d (issued = hoje, duration=60d)
        $outside = Prescription::factory()->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $medico->id,
            'patient_id' => $patient->id,
            'type' => PrescriptionType::Common,
            'status' => PrescriptionStatus::Active,
            'source' => 'manual',
            'issued_at' => Carbon::today(),
            'expires_at' => Carbon::today()->addDays(60),
        ]);

        // Expired: issued há 31d, expires = issued+30 = ontem
        $expiredIssued = Carbon::today()->subDays(31);
        $expired = Prescription::factory()->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $medico->id,
            'patient_id' => $patient->id,
            'type' => PrescriptionType::Common,
            'status' => PrescriptionStatus::Active,
            'source' => 'manual',
            'issued_at' => $expiredIssued,
            'expires_at' => $expiredIssued->copy()->addDays(30),
        ]);

        $response = $this->getJson(
            $this->tenantUrl($tenant, '/prescription-reports'),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($inside->id, $ids, 'Inside window (today+10d) should be included.');
        $this->assertContains($boundary->id, $ids, 'Boundary (today+30d) should be included.');
        $this->assertNotContains($outside->id, $ids, 'Outside window (today+60d) should be excluded.');
        $this->assertNotContains($expired->id, $ids, 'Expired (yesterday) should be excluded from default window.');
    }

    public function test_report_custom_expires_after_expires_before_overrides_default_window(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('rpt-custom-window', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $patient = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        // issued há 35d, expires há 5d (passado)
        $pastIssued = Carbon::today()->subDays(35);
        $past = Prescription::factory()->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $medico->id,
            'patient_id' => $patient->id,
            'type' => PrescriptionType::Common,
            'status' => PrescriptionStatus::Active,
            'source' => 'manual',
            'issued_at' => $pastIssued,
            'expires_at' => $pastIssued->copy()->addDays(30),
        ]);

        $expiresAfter = Carbon::today()->subDays(10)->toDateString();
        $expiresBefore = Carbon::today()->toDateString();

        $response = $this->getJson(
            $this->tenantUrl($tenant, "/prescription-reports?expires_after={$expiresAfter}&expires_before={$expiresBefore}"),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($past->id, $ids, 'Custom window should include past prescription.');
    }

    public function test_report_is_ordered_by_expires_at_asc_by_default(): void
    {
        [$tenant, $medico] = $this->tenantAndUserForRole('rpt-order', 'medico');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        $patient = Paciente::factory()->create(['tenant_id' => $tenant->id]);

        // Later: issued hoje, expires +30d
        $later = Prescription::factory()->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $medico->id,
            'patient_id' => $patient->id,
            'type' => PrescriptionType::Common,
            'status' => PrescriptionStatus::Active,
            'source' => 'manual',
            'issued_at' => Carbon::today(),
            'expires_at' => Carbon::today()->addDays(30),
        ]);

        // Sooner: issued 25d atrás, expires daqui 5d
        $soonerIssued = Carbon::today()->subDays(25);
        $sooner = Prescription::factory()->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $medico->id,
            'patient_id' => $patient->id,
            'type' => PrescriptionType::Common,
            'status' => PrescriptionStatus::Active,
            'source' => 'manual',
            'issued_at' => $soonerIssued,
            'expires_at' => $soonerIssued->copy()->addDays(30),
        ]);

        $response = $this->getJson(
            $this->tenantUrl($tenant, '/prescription-reports'),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $soonerIdx = array_search($sooner->id, $ids);
        $laterIdx = array_search($later->id, $ids);

        $this->assertLessThan($laterIdx, $soonerIdx, 'Sooner expiry should come before later expiry.');
    }

    public function test_report_returns_403_without_prescription_view_ability(): void
    {
        [$tenant, $recepcionista] = $this->tenantAndUserForRole('rpt-403', 'recepcionista');
        $tenant->update(['settings' => ['modules' => ['prescriptions' => ['enabled' => true]]]]);

        // Remove ability e limpa cache do Spatie
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $recepcionista->revokePermissionTo('prescription.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Cria usuário sem prescription.view para garantir que a role não sobrescreve
        // (revokePermissionTo remove do user, mas role ainda pode ter — usamos usuário fresco sem a role)
        $userSemPermissao = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->app->instance('tenant', $tenant);
        Sanctum::actingAs($userSemPermissao, ['*']);

        $response = $this->getJson(
            $this->tenantUrl($tenant, '/prescription-reports'),
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertForbidden();
    }
}
