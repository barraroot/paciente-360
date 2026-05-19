<?php

namespace Tests\Feature\Prescription;

use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class PrescriptionFoundationalSchemaTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    public function test_prescription_foundational_tables_are_created(): void
    {
        foreach ([
            'prescriptions',
            'prescription_items',
            'prescription_alerts',
            'prescription_renewals',
            'patient_professional_preferences',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Tabela {$table} deve existir.");
        }
    }

    public function test_prescriptions_reject_alert_disabled_for_non_common_types(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('CHECK constraint validada apenas em PostgreSQL.');
        }

        [$tenant, $patient, $professional] = $this->createPrescriptionDependencies();

        $this->expectException(QueryException::class);

        DB::table('prescriptions')->insert($this->validPrescriptionPayload(
            tenant: $tenant,
            patient: $patient,
            professional: $professional,
            overrides: [
                'type' => 'special',
                'expires_at' => now()->addDays(30)->toDateString(),
                'alert_disabled' => true,
            ],
        ));
    }

    public function test_controlled_prescriptions_accept_a_single_item_only(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Trigger validado apenas em PostgreSQL.');
        }

        [$tenant, $patient, $professional] = $this->createPrescriptionDependencies();

        $prescriptionId = DB::table('prescriptions')->insertGetId($this->validPrescriptionPayload(
            tenant: $tenant,
            patient: $patient,
            professional: $professional,
            overrides: [
                'type' => 'controlled',
                'expires_at' => now()->addDays(30)->toDateString(),
            ],
        ));

        DB::table('prescription_items')->insert($this->validPrescriptionItemPayload($prescriptionId, 0));

        $this->expectException(QueryException::class);

        DB::table('prescription_items')->insert($this->validPrescriptionItemPayload($prescriptionId, 1));
    }

    public function test_alerts_enforce_idempotency_per_prescription_and_type(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Enum/unique constraint validadas apenas em PostgreSQL.');
        }

        [$tenant, $patient, $professional] = $this->createPrescriptionDependencies();

        $prescriptionId = DB::table('prescriptions')->insertGetId($this->validPrescriptionPayload(
            tenant: $tenant,
            patient: $patient,
            professional: $professional,
        ));

        DB::table('prescription_alerts')->insert($this->validPrescriptionAlertPayload($tenant, $prescriptionId));

        $this->expectException(QueryException::class);

        DB::table('prescription_alerts')->insert($this->validPrescriptionAlertPayload($tenant, $prescriptionId));
    }

    /**
     * @return array{0: Tenant, 1: Paciente, 2: User}
     */
    private function createPrescriptionDependencies(): array
    {
        $tenant = $this->createTenant();
        $professional = $this->createUserForTenant($tenant);
        $patient = Paciente::factory()->forTenant($tenant)->create();

        return [$tenant, $patient, $professional];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validPrescriptionPayload(Tenant $tenant, Paciente $patient, User $professional, array $overrides = []): array
    {
        $now = now();
        $issuedAt = $now->toDateString();

        return array_merge([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'professional_id' => $professional->id,
            'appointment_id' => null,
            'type' => 'common',
            'status' => 'active',
            'issued_at' => $issuedAt,
            'expires_at' => $now->copy()->addDays(30)->toDateString(),
            'renewed_from_id' => null,
            'source' => 'manual',
            'cancelled_at' => null,
            'cancelled_by_user_id' => null,
            'cancellation_reason_category' => null,
            'cancellation_reason' => null,
            'alert_disabled' => false,
            'notes' => null,
            'pdf_path' => null,
            'pdf_version' => 0,
            'imported_at' => null,
            'imported_source' => null,
            'historical_external_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPrescriptionItemPayload(int $prescriptionId, int $sortOrder): array
    {
        $now = now();

        return [
            'prescription_id' => $prescriptionId,
            'medication_name' => 'Medicamento Teste',
            'concentration' => '50mg',
            'pharmaceutical_form' => 'comprimido',
            'posology' => '1 comprimido a cada 12h',
            'quantity' => '30 comprimidos',
            'treatment_duration' => '30 dias',
            'sort_order' => $sortOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validPrescriptionAlertPayload(Tenant $tenant, int $prescriptionId): array
    {
        $now = now();

        return [
            'prescription_id' => $prescriptionId,
            'tenant_id' => $tenant->id,
            'alert_type' => 'days15',
            'status' => 'pending',
            'scheduled_for' => now()->addDays(15)->toDateString(),
            'dispatched_at' => null,
            'channel_id' => null,
            'message_id' => null,
            'failure_reason' => null,
            'skip_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
