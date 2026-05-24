<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Domain\Privacy\Events\DireitoEsquecimentoExecutado;
use App\Domain\Privacy\Models\ForgettingRequest;
use App\Domain\Privacy\Models\ForgettingStatus;
use App\Domain\Privacy\Services\ForgettingExecutor;
use App\Models\Paciente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T062 (Fase 8 — Lote A US-13.2)** — GATE 3 (Constitution): mapa de anonimização Q26.
 *
 * Valida que `ForgettingExecutor::execute()` aplica corretamente as 3 categorias
 * do mapa:
 *   - ANONIMIZAR (placeholders fixos por campo).
 *   - DELETAR (campos físicos zerados).
 *   - PRESERVAR (categorias sob obrigação legal não tocadas).
 */
class RightToBeForgottenMapTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_execute_anonymizes_patient_pii_fields(): void
    {
        Event::fake([DireitoEsquecimentoExecutado::class]);

        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-forget-anon', 'admin-clinica');

        $patient = Paciente::factory()->state([
            'tenant_id' => $tenant->id,
            'nome' => 'Maria Silva',
            'cpf' => '123.456.789-00',
            'telefone_primario' => '11999998888',
            'email' => 'maria@example.com',
        ])->create();

        $request = ForgettingRequest::factory()
            ->state(['tenant_id' => $tenant->id, 'patient_id' => $patient->id])
            ->open()
            ->create();

        /** @var ForgettingExecutor $executor */
        $executor = app(ForgettingExecutor::class);
        $result = $executor->execute($request, $admin);

        $patient->refresh();

        $this->assertSame("Paciente Anonimizado #{$patient->id}", $patient->nome);
        $this->assertSame('000.000.000-00', $patient->cpf);
        $this->assertSame('00000000000', $patient->telefone_primario);
        $this->assertNull($patient->email);

        $this->assertSame(ForgettingStatus::Executed, $result->status);
        $this->assertNotNull($result->executed_at);
        $this->assertSame($admin->id, $result->executed_by_user_id);

        Event::assertDispatched(DireitoEsquecimentoExecutado::class);
    }

    public function test_execute_deletes_endereco_jsonb(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-forget-delete', 'admin-clinica');

        $patient = Paciente::factory()->state([
            'tenant_id' => $tenant->id,
            'endereco' => ['rua' => 'Rua das Flores', 'numero' => '123'],
        ])->create();

        $request = ForgettingRequest::factory()
            ->state(['tenant_id' => $tenant->id, 'patient_id' => $patient->id])
            ->open()
            ->create();

        /** @var ForgettingExecutor $executor */
        $executor = app(ForgettingExecutor::class);
        $executor->execute($request, $admin);

        $patient->refresh();
        // DB grava NULL (PII removida); o cast AsJsonArray lê JSONB null como [].
        $this->assertEmpty($patient->endereco);
    }

    public function test_execute_preserves_fields_under_legal_obligation_in_snapshot(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-forget-preserve', 'admin-clinica');

        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();
        $request = ForgettingRequest::factory()
            ->state(['tenant_id' => $tenant->id, 'patient_id' => $patient->id])
            ->open()
            ->create();

        /** @var ForgettingExecutor $executor */
        $executor = app(ForgettingExecutor::class);
        $result = $executor->execute($request, $admin);

        $preserved = $result->fields_preserved_reason ?? [];

        $reasons = collect($preserved)->pluck('reason')->all();
        $this->assertContains('portaria_344_98', $reasons, 'Receitas controladas devem ser preservadas (2a).');
        $this->assertContains('lei_12682_2012', $reasons, 'Registros financeiros devem ser preservados (5a).');
        $this->assertContains('lgpd_art_16', $reasons, 'Audit logs devem ser preservados (1a).');
    }

    public function test_execute_is_idempotent_and_blocks_double_execution(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-forget-idem', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        $request = ForgettingRequest::factory()
            ->state(['tenant_id' => $tenant->id, 'patient_id' => $patient->id])
            ->executed()
            ->create();

        /** @var ForgettingExecutor $executor */
        $executor = app(ForgettingExecutor::class);

        $this->expectException(\RuntimeException::class);
        $executor->execute($request, $admin);
    }

    public function test_deny_marks_request_with_reason(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-forget-deny', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();
        $request = ForgettingRequest::factory()
            ->state(['tenant_id' => $tenant->id, 'patient_id' => $patient->id])
            ->open()
            ->create();

        /** @var ForgettingExecutor $executor */
        $executor = app(ForgettingExecutor::class);
        $denied = $executor->deny($request, $admin, 'Identidade não confirmada via documento.');

        $this->assertSame(ForgettingStatus::Denied, $denied->status);
        $this->assertSame('Identidade não confirmada via documento.', $denied->denial_reason);
        $this->assertSame($admin->id, $denied->executed_by_user_id);
    }
}
