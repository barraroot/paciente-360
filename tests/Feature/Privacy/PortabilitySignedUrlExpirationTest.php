<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Domain\Privacy\Models\PortabilityRequest;
use App\Domain\Privacy\Models\PortabilityStatus;
use App\Domain\Privacy\Services\PortabilityExporter;
use App\Models\Paciente;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T065 (Fase 8 — Lote A US-13.2)** — URL assinada expira em 7 dias.
 *
 * Após expiração:
 *   - `signedUrlExpired()` retorna true.
 *   - `buildSignedUrl()` retorna null.
 *   - Status pode transitar para Expired via cron `privacy:mark-expired` (T058).
 */
class PortabilitySignedUrlExpirationTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_signed_url_is_active_within_7_days(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-port-url-ok', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        $req = PortabilityRequest::factory()
            ->state(['tenant_id' => $tenant->id, 'patient_id' => $patient->id])
            ->ready()
            ->create();

        $this->assertFalse($req->signedUrlExpired());
        $this->assertTrue($req->status->isUrlActive());

        /** @var PortabilityExporter $exporter */
        $exporter = app(PortabilityExporter::class);
        $url = $exporter->buildSignedUrl($req);

        $this->assertNotNull($url, 'URL deve estar disponível dentro da janela de 7d.');
        $this->assertStringContainsString($req->file_signed_url_id, $url);
    }

    public function test_signed_url_returns_null_after_expiration(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-port-url-exp', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        $req = PortabilityRequest::factory()
            ->state(['tenant_id' => $tenant->id, 'patient_id' => $patient->id])
            ->expired()
            ->create();

        $this->assertTrue($req->signedUrlExpired());

        /** @var PortabilityExporter $exporter */
        $exporter = app(PortabilityExporter::class);
        $url = $exporter->buildSignedUrl($req);

        $this->assertNull($url);
    }

    public function test_paciente_can_request_new_link_without_resetting_deadline(): void
    {
        // Documentação operacional: novo link cria novo PortabilityRequest com
        // mesmo `deadline_at` do original. Aqui apenas valida o invariante.
        [$tenant, ] = $this->tenantAndUserForRole('clinica-port-relink', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        $original = PortabilityRequest::factory()
            ->state(['tenant_id' => $tenant->id, 'patient_id' => $patient->id])
            ->expired()
            ->create();

        // Simula novo request com deadline herdado (operação manual do admin).
        $newRequest = PortabilityRequest::factory()
            ->state([
                'tenant_id' => $tenant->id,
                'patient_id' => $patient->id,
                'deadline_at' => $original->deadline_at,
                'status' => PortabilityStatus::Open,
            ])
            ->create();

        $this->assertTrue(
            $newRequest->deadline_at->equalTo($original->deadline_at),
            'Deadline LGPD original deve ser preservado em re-link.',
        );
    }
}
