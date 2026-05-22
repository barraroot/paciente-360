<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Domain\Privacy\Events\PoliticaPseudonimizacaoAuditada;
use App\Domain\Privacy\Models\PseudonymizationAuditMode;
use App\Domain\Privacy\Services\PseudonymizationAuditor;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T077 (Fase 8 — Lote A US-13.3)** — Cobertura estática do auditor (complementa Gate 4).
 *
 * Diferença vs {@see Tests\Feature\Constitutional\EventsForAiPseudonymizationTest} (T012):
 *   - T012 é o GATE DE CI — falha o build se evento não conforme entrar na config.
 *   - T077 valida que o SERVICE registra corretamente o resultado no DB +
 *     emite evento auditável, mesmo com config vazia ou com evento inválido.
 */
class PseudonymizationStaticAuditTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_static_audit_with_empty_config_is_compliant_with_zero_findings(): void
    {
        Event::fake([PoliticaPseudonimizacaoAuditada::class]);
        Config::set('finalization.ai_consumed_events', []);

        /** @var PseudonymizationAuditor $auditor */
        $auditor = app(PseudonymizationAuditor::class);
        $audit = $auditor->runStaticReflection();

        $this->assertSame(0, $audit->total_events_scanned);
        $this->assertSame(0, $audit->non_conformant_events);
        $this->assertTrue($audit->isCompliant());
        $this->assertSame(PseudonymizationAuditMode::StaticReflection, $audit->mode);
        $this->assertNull($audit->findings);

        Event::assertDispatched(PoliticaPseudonimizacaoAuditada::class);
    }

    public function test_static_audit_detects_missing_marker_interface(): void
    {
        Config::set('finalization.ai_consumed_events', [
            // Evento real do Lote A — implementa ContainsNoClinicalData ✅
            \App\Domain\Privacy\Events\ConsentimentoRegistrado::class,
            // Classe stdClass — NÃO implementa marker ❌
            \stdClass::class,
        ]);

        /** @var PseudonymizationAuditor $auditor */
        $auditor = app(PseudonymizationAuditor::class);
        $audit = $auditor->runStaticReflection();

        $this->assertSame(2, $audit->total_events_scanned);
        $this->assertSame(1, $audit->non_conformant_events);
        $this->assertFalse($audit->isCompliant());

        $findings = $audit->findings;
        $this->assertIsArray($findings);
        $this->assertCount(1, $findings);
        $this->assertSame(\stdClass::class, $findings[0]['event_class']);
        $this->assertSame('missing_marker_interface', $findings[0]['reason']);
    }

    public function test_static_audit_detects_non_existent_class(): void
    {
        Config::set('finalization.ai_consumed_events', [
            'App\\Events\\NonExistent\\EventoFantasma',
        ]);

        /** @var PseudonymizationAuditor $auditor */
        $auditor = app(PseudonymizationAuditor::class);
        $audit = $auditor->runStaticReflection();

        $this->assertSame(1, $audit->non_conformant_events);
        $findings = $audit->findings ?? [];
        $this->assertSame('class_not_found', $findings[0]['reason'] ?? null);
    }

    public function test_audit_records_audited_by_user_when_provided(): void
    {
        [, $admin] = $this->tenantAndUserForRole('clinica-static-by-user', 'admin-clinica');

        Config::set('finalization.ai_consumed_events', []);

        /** @var PseudonymizationAuditor $auditor */
        $auditor = app(PseudonymizationAuditor::class);
        $audit = $auditor->runStaticReflection(auditedByUserId: $admin->id);

        $this->assertSame($admin->id, $audit->audited_by_user_id);
    }
}
