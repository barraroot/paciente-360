<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Domain\Privacy\Models\PseudonymizationAuditMode;
use App\Domain\Privacy\Services\PseudonymizationAuditor;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T078 (Fase 8 — Lote A US-13.3)** — Q29 — runtime replay detecta PII.
 *
 * Cenários:
 *   1. Audit log SEM PII → 0 findings, compliant.
 *   2. Audit log COM CPF no payload → 1 finding com pattern='cpf'.
 *   3. Audit log COM múltiplos PIIs → findings agregados sem expor valor real.
 */
class PseudonymizationRuntimeReplayTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_replay_with_no_pii_returns_compliant(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-replay-clean', 'admin-clinica');

        // Setup — audit logs limpos (sem PII).
        AuditLog::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'actor_type' => 'user',
            'action' => 'paciente.viewed',
            'payload' => ['paciente_id' => 42, 'channel' => 'web'],
            'created_at' => Carbon::now()->subDay(),
        ]);

        /** @var PseudonymizationAuditor $auditor */
        $auditor = app(PseudonymizationAuditor::class);
        $audit = $auditor->runRuntimeReplay(samplePercent: 100);

        $this->assertSame(PseudonymizationAuditMode::RuntimeReplay, $audit->mode);
        $this->assertSame(0, $audit->non_conformant_events);
        $this->assertTrue($audit->isCompliant());
    }

    public function test_replay_detects_cpf_in_audit_log_payload(): void
    {
        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-replay-cpf', 'admin-clinica');

        // Audit log COM CPF em texto plano — simulação de "evento mal pseudonimizado".
        AuditLog::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'actor_type' => 'user',
            'action' => 'paciente.created',
            'payload' => [
                'paciente_id' => 99,
                'cpf_leaked' => '123.456.789-09', // INTENCIONAL — simula vazamento.
            ],
            'created_at' => Carbon::now()->subHours(2),
        ]);

        /** @var PseudonymizationAuditor $auditor */
        $auditor = app(PseudonymizationAuditor::class);
        $audit = $auditor->runRuntimeReplay(samplePercent: 100);

        $this->assertGreaterThan(0, $audit->non_conformant_events);
        $this->assertFalse($audit->isCompliant());

        $findings = $audit->findings ?? [];
        $cpfFinding = collect($findings)->firstWhere('pattern', 'cpf');
        $this->assertNotNull($cpfFinding, 'Replay deveria detectar CPF.');
        // Critical: finding NÃO deve expor o valor real do CPF.
        $this->assertSame('cpf', $cpfFinding['pattern']);
        $this->assertSame('paciente.created', $cpfFinding['action']);
        $this->assertSame('cpf_leaked', $cpfFinding['field_path']);
        $this->assertSame('critical', $cpfFinding['severity']);
        $this->assertArrayNotHasKey('value', $cpfFinding);
    }

    public function test_replay_handles_empty_audit_log_universe(): void
    {
        // Nenhum audit log no DB.
        /** @var PseudonymizationAuditor $auditor */
        $auditor = app(PseudonymizationAuditor::class);
        $audit = $auditor->runRuntimeReplay(samplePercent: 1);

        $this->assertSame(0, $audit->non_conformant_events);
        $this->assertTrue($audit->isCompliant());
    }
}
