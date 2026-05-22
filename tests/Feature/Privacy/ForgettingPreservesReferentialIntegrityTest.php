<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Domain\Privacy\Models\ConsentRecord;
use App\Domain\Privacy\Models\ForgettingRequest;
use App\Domain\Privacy\Services\ForgettingExecutor;
use App\Models\Paciente;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T063 (Fase 8 — Lote A US-13.2)** — R-8-1 mitigação.
 *
 * Após anonimização, referências (consent_records.patient_id, prescriptions.patient_id,
 * appointments.patient_id) ainda devem resolver — anonimização não DELETA a linha,
 * apenas substitui campos. Isto preserva FKs e o histórico auditável.
 */
class ForgettingPreservesReferentialIntegrityTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_consent_records_remain_valid_after_forgetting(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-fk-ok', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        // Setup — consent ativo do paciente.
        $consent = ConsentRecord::factory()
            ->marketing()
            ->state(['tenant_id' => $tenant->id, 'patient_id' => $patient->id])
            ->create();

        $request = ForgettingRequest::factory()
            ->state(['tenant_id' => $tenant->id, 'patient_id' => $patient->id])
            ->open()
            ->create();

        /** @var ForgettingExecutor $executor */
        $executor = app(ForgettingExecutor::class);
        $executor->execute($request, $admin);

        // Consent ainda existe e ainda referencia o patient.
        $consent->refresh();
        $this->assertSame($patient->id, $consent->patient_id);

        // Paciente continua existindo (linha preservada, campos anonimizados).
        $this->assertDatabaseHas('pacientes', ['id' => $patient->id]);

        // Forgetting request continua íntegro.
        $request->refresh();
        $this->assertDatabaseHas('forgetting_requests', [
            'id' => $request->id,
            'patient_id' => $patient->id,
        ]);
    }
}
