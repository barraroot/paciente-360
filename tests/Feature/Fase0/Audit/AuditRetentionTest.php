<?php

namespace Tests\Feature\Fase0\Audit;

use App\Jobs\Audit\ArchiveAuditLogsJob;
use App\Jobs\Audit\DeleteExpiredAuditLogsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * T264 — Feature tests dos jobs de retenção (US-2.4 — FR-038, LGPD Art. 16).
 *
 * Cobre:
 *  - Archive move logs > 2 anos para `audit_logs_cold`.
 *  - Delete remove de `audit_logs_cold` logs > 5 anos.
 *  - Ambos os jobs são idempotentes (rodar 2x não altera estado final).
 *
 * Estratégia para popular timestamps customizados em `audit_logs`:
 *  - Os triggers PG só rejeitam UPDATE/DELETE; INSERT com `created_at`
 *    arbitrário funciona normalmente. Usamos `DB::table()->insert()`
 *    diretamente.
 */
class AuditRetentionTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    /**
     * Insere uma linha em `audit_logs` com `created_at` customizado.
     */
    private function insertHot(int $tenantId, string $createdAt, string $action = 'retention.test'): int
    {
        return (int) DB::table('audit_logs')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => null,
            'actor_type' => 'system',
            'action' => $action,
            'auditable_type' => null,
            'auditable_id' => null,
            'payload' => '{}',
            'ip' => null,
            'user_agent' => null,
            'request_id' => null,
            'created_at' => $createdAt,
        ]);
    }

    /**
     * Insere uma linha em `audit_logs_cold` com `created_at` customizado.
     */
    private function insertCold(int $tenantId, string $createdAt, string $action = 'retention.cold'): int
    {
        return (int) DB::table('audit_logs_cold')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => null,
            'actor_type' => 'system',
            'action' => $action,
            'auditable_type' => null,
            'auditable_id' => null,
            'payload' => '{}',
            'ip' => null,
            'user_agent' => null,
            'request_id' => null,
            'created_at' => $createdAt,
        ]);
    }

    /** @test */
    public function test_archive_moves_logs_older_than_2_years_to_cold(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-retention-archive']);

        $oldDate = now()->subYears(3)->toIso8601String();
        $recentDate = now()->subDays(1)->toIso8601String();

        $this->insertHot($tenant->id, $oldDate, 'old.event');
        $this->insertHot($tenant->id, $recentDate, 'recent.event');

        ArchiveAuditLogsJob::dispatchSync();

        // Hot: só ficou o recente.
        $this->assertDatabaseHas('audit_logs', ['action' => 'recent.event']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'old.event']);

        // Cold: recebeu o antigo.
        $this->assertDatabaseHas('audit_logs_cold', ['action' => 'old.event']);
        $this->assertDatabaseMissing('audit_logs_cold', ['action' => 'recent.event']);
    }

    /** @test */
    public function test_delete_removes_logs_older_than_5_years_from_cold(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-retention-delete']);

        $veryOldDate = now()->subYears(6)->toIso8601String();
        $oldDate = now()->subYear()->toIso8601String();

        $this->insertCold($tenant->id, $veryOldDate, 'cold.expired');
        $this->insertCold($tenant->id, $oldDate, 'cold.valid');

        DeleteExpiredAuditLogsJob::dispatchSync();

        $this->assertDatabaseMissing('audit_logs_cold', ['action' => 'cold.expired']);
        $this->assertDatabaseHas('audit_logs_cold', ['action' => 'cold.valid']);
    }

    /** @test */
    public function test_archive_is_idempotent(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-retention-idem']);

        $oldDate = now()->subYears(3)->toIso8601String();

        $this->insertHot($tenant->id, $oldDate, 'idem.event');

        ArchiveAuditLogsJob::dispatchSync();
        ArchiveAuditLogsJob::dispatchSync();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'idem.event']);
        $this->assertSame(
            1,
            DB::table('audit_logs_cold')->where('action', 'idem.event')->count(),
            'Cold deve ter exatamente 1 cópia após 2 execuções do archive.'
        );
    }

    /** @test */
    public function test_delete_is_idempotent(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-retention-del-idem']);

        $veryOldDate = now()->subYears(6)->toIso8601String();
        $this->insertCold($tenant->id, $veryOldDate, 'del.idem.event');

        DeleteExpiredAuditLogsJob::dispatchSync();
        DeleteExpiredAuditLogsJob::dispatchSync();

        $this->assertDatabaseMissing('audit_logs_cold', ['action' => 'del.idem.event']);
    }

    /** @test */
    public function test_archive_preserves_payload_and_tenant_id(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-retention-preserve']);

        $oldDate = now()->subYears(3)->toIso8601String();

        DB::table('audit_logs')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'actor_type' => 'system',
            'action' => 'preserve.event',
            'payload' => json_encode(['k' => 'v']),
            'ip' => '10.0.0.1',
            'user_agent' => 'test-ua',
            'request_id' => 'req-123',
            'created_at' => $oldDate,
        ]);

        ArchiveAuditLogsJob::dispatchSync();

        $cold = DB::table('audit_logs_cold')->where('action', 'preserve.event')->first();

        $this->assertNotNull($cold);
        $this->assertSame($tenant->id, (int) $cold->tenant_id);
        $this->assertSame('10.0.0.1', $cold->ip);
        $this->assertSame('test-ua', $cold->user_agent);
        $this->assertSame('req-123', $cold->request_id);
    }
}
