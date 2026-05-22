<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Domain\Privacy\Events\PortabilidadeDadosExecutada;
use App\Domain\Privacy\Models\PortabilityRequest;
use App\Domain\Privacy\Models\PortabilityStatus;
use App\Domain\Privacy\Services\PortabilityExporter;
use App\Models\Paciente;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T064 (Fase 8 — Lote A US-13.2)** — AC-13.2.8 (Q28 portabilidade).
 *
 * Valida que `PortabilityExporter::execute()`:
 *   - Gera arquivo JSON v1.0 com a estrutura esperada.
 *   - Cria URL assinada com TTL 7 dias.
 *   - Emite PortabilidadeDadosExecutada.
 *   - Status transita open → generating → ready.
 */
class PortabilityArchiveGenerationTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('s3');
    }

    public function test_buildArchive_returns_schema_v1_structure(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-port-build', 'admin-clinica');
        $patient = Paciente::factory()->state([
            'tenant_id' => $tenant->id,
            'nome' => 'Maria Teste',
        ])->create();

        /** @var PortabilityExporter $exporter */
        $exporter = app(PortabilityExporter::class);
        $archive = $exporter->buildArchive($patient);

        $this->assertSame('1.0', $archive['schema_version']);
        $this->assertArrayHasKey('exported_at', $archive);
        $this->assertArrayHasKey('tenant', $archive);
        $this->assertArrayHasKey('patient', $archive);
        $this->assertArrayHasKey('consents', $archive);
        $this->assertArrayHasKey('timeline', $archive);
        $this->assertArrayHasKey('appointments', $archive);
        $this->assertArrayHasKey('prescriptions', $archive);

        $this->assertSame('Maria Teste', $archive['patient']['nome']);
        $this->assertSame($tenant->id, $archive['tenant']['id']);
    }

    public function test_execute_generates_file_with_signed_url_ttl_7_days(): void
    {
        Event::fake([PortabilidadeDadosExecutada::class]);

        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-port-exec', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        $request = PortabilityRequest::factory()
            ->state(['tenant_id' => $tenant->id, 'patient_id' => $patient->id])
            ->open()
            ->create();

        /** @var PortabilityExporter $exporter */
        $exporter = new PortabilityExporter('s3');
        $ready = $exporter->execute($request, $admin);

        $this->assertSame(PortabilityStatus::Ready, $ready->status);
        $this->assertNotNull($ready->file_path);
        $this->assertNotNull($ready->file_signed_url_id);
        $this->assertNotNull($ready->url_expires_at);
        $this->assertGreaterThan(0, $ready->file_size_bytes);

        // TTL 7 dias (config finalization.pdf_signed_url_ttl_days = 7).
        $expectedExpiry = $ready->executed_at->copy()->addDays(7);
        $this->assertTrue(
            $ready->url_expires_at->diffInMinutes($expectedExpiry, false) < 5,
            'URL deve expirar em ~7 dias da execução.',
        );

        Storage::disk('s3')->assertExists($ready->file_path);

        Event::assertDispatched(
            PortabilidadeDadosExecutada::class,
            fn (PortabilidadeDadosExecutada $e): bool => $e->portabilityRequestId === $ready->id,
        );
    }
}
