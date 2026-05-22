<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Domain\Privacy\Events\ConsentimentoRecusado;
use App\Domain\Privacy\Events\ConsentimentoRegistrado;
use App\Domain\Privacy\Models\ConsentFinalidade;
use App\Domain\Privacy\Models\ConsentRecord;
use App\Domain\Privacy\Models\ConsentState;
use App\Domain\Privacy\Services\ConsentService;
use App\Models\Paciente;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T035 (Fase 8 — Lote A US-13.1)** — Cobre AC-13.1.1 → AC-13.1.4.
 *
 * Cenários validados:
 *   1. ConsentService::record() persiste granted e emite ConsentimentoRegistrado.
 *   2. ConsentService::refuse() persiste refused e emite ConsentimentoRecusado.
 *   3. Idempotência — record() chamado 2× para mesmo (patient, finalidade) não duplica.
 *   4. Endpoint POST /api/v1/privacy/consents cria registro com 201.
 */
class ConsentRecordingTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_service_records_granted_consent_and_emits_event(): void
    {
        Event::fake([ConsentimentoRegistrado::class]);

        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-grant', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        /** @var ConsentService $service */
        $service = app(ConsentService::class);

        $record = $service->record(
            patient: $patient,
            channel: 'whatsapp',
            finalidade: ConsentFinalidade::Marketing,
            evidenceSnapshot: ['text' => 'Aceito receber comunicações.'],
        );

        $this->assertSame(ConsentState::Granted, $record->state);
        $this->assertSame(ConsentFinalidade::Marketing, $record->finalidade);
        $this->assertNotNull($record->granted_at);

        $this->assertDatabaseHas('consent_records', [
            'id' => $record->id,
            'patient_id' => $patient->id,
            'tenant_id' => $tenant->id,
            'state' => ConsentState::Granted->value,
            'finalidade' => ConsentFinalidade::Marketing->value,
        ]);

        Event::assertDispatched(
            ConsentimentoRegistrado::class,
            fn (ConsentimentoRegistrado $e): bool => $e->patientId === $patient->id
                && $e->finalidade === ConsentFinalidade::Marketing,
        );
    }

    public function test_service_records_refusal_and_emits_event(): void
    {
        Event::fake([ConsentimentoRecusado::class]);

        [$tenant, ] = $this->tenantAndUserForRole('clinica-refuse', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        /** @var ConsentService $service */
        $service = app(ConsentService::class);

        $record = $service->refuse(
            patient: $patient,
            channel: 'whatsapp',
            finalidade: ConsentFinalidade::Marketing,
        );

        $this->assertSame(ConsentState::Refused, $record->state);
        $this->assertNull($record->granted_at);
        $this->assertNull($record->revoked_at);

        Event::assertDispatched(ConsentimentoRecusado::class);
    }

    public function test_service_record_is_idempotent_when_active_consent_exists(): void
    {
        Event::fake([ConsentimentoRegistrado::class]);

        [$tenant, ] = $this->tenantAndUserForRole('clinica-idempotent', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        /** @var ConsentService $service */
        $service = app(ConsentService::class);

        $first = $service->record($patient, 'whatsapp', ConsentFinalidade::Marketing);
        $second = $service->record($patient, 'whatsapp', ConsentFinalidade::Marketing);

        $this->assertSame($first->id, $second->id, 'Service deve retornar o consent existente sem duplicar.');

        $this->assertCount(
            1,
            ConsentRecord::query()
                ->where('patient_id', $patient->id)
                ->where('finalidade', ConsentFinalidade::Marketing->value)
                ->granted()
                ->get(),
        );

        // O segundo call deve NÃO disparar evento (idempotent fast path).
        Event::assertDispatchedTimes(ConsentimentoRegistrado::class, 1);
    }

    public function test_endpoint_creates_consent_with_201(): void
    {
        Event::fake([ConsentimentoRegistrado::class]);

        [$tenant, ] = $this->tenantAndUserForRole('clinica-endpoint-create', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        $response = $this->postJson(
            $this->tenantUrl($tenant, '/privacy/consents'),
            [
                'patient_id' => $patient->id,
                'channel' => 'web',
                'finalidade' => 'marketing',
                'state' => 'granted',
                'evidence_snapshot' => ['text' => 'Aceito comunicações da clínica.'],
                'terms_version' => '1.2',
            ],
            ['X-Tenant-Slug' => $tenant->slug],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.finalidade', 'marketing');
        $response->assertJsonPath('data.state', 'granted');
        $response->assertJsonPath('data.terms_version', '1.2');

        Event::assertDispatched(ConsentimentoRegistrado::class);
    }

    public function test_transacional_is_separately_granted_from_marketing(): void
    {
        Event::fake([ConsentimentoRegistrado::class]);

        [$tenant, ] = $this->tenantAndUserForRole('clinica-hierarchy', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        /** @var ConsentService $service */
        $service = app(ConsentService::class);

        $service->record($patient, 'web', ConsentFinalidade::Transacional);
        $service->record($patient, 'web', ConsentFinalidade::Marketing);

        $this->assertTrue($service->hasGranted($patient->id, ConsentFinalidade::Transacional));
        $this->assertTrue($service->hasGranted($patient->id, ConsentFinalidade::Marketing));

        // Revogar marketing NÃO afeta transacional (Q25/AC-13.1.3).
        $service->revoke($patient, ConsentFinalidade::Marketing, 'web');

        $this->assertTrue($service->hasGranted($patient->id, ConsentFinalidade::Transacional));
        $this->assertFalse($service->hasGranted($patient->id, ConsentFinalidade::Marketing));
    }
}
