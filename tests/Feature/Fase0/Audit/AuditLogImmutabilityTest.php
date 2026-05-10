<?php

namespace Tests\Feature\Fase0\Audit;

use App\Exceptions\AuditLogIsImmutableException;
use App\Models\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T070 — Testa a imutabilidade da tabela `audit_logs`.
 *
 * Estratégia de defesa em profundidade:
 *  1. Trigger PG `audit_logs_immutable()` rejeita UPDATE/DELETE em nível de BD.
 *  2. Model `AuditLog` lança `AuditLogIsImmutableException` ANTES de atingir o BD.
 *
 * @see specs/001-fundacao-multitenant/data-model.md § 9
 * @see App\Models\AuditLog
 * @see App\Exceptions\AuditLogIsImmutableException
 */
class AuditLogImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Dados mínimos para inserção direta na tabela (sem usar o Model).
     *
     * @return array<string, mixed>
     */
    private function minimalRow(): array
    {
        return [
            'actor_type' => 'system',
            'action' => 'test.immutability',
            'payload' => '{}',
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * O trigger PG deve rejeitar UPDATE em `audit_logs` com mensagem
     * "audit_logs is append-only".
     */
    public function test_cannot_update_audit_log_at_database_level(): void
    {
        $id = \DB::table('audit_logs')->insertGetId($this->minimalRow());

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/audit_logs is append-only/i');

        \DB::table('audit_logs')->where('id', $id)->update(['action' => 'hack']);
    }

    /**
     * O trigger PG deve rejeitar DELETE em `audit_logs` com mensagem
     * "audit_logs is append-only".
     */
    public function test_cannot_delete_audit_log_at_database_level(): void
    {
        $id = \DB::table('audit_logs')->insertGetId($this->minimalRow());

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/audit_logs is append-only/i');

        \DB::table('audit_logs')->where('id', $id)->delete();
    }

    /**
     * O Model `AuditLog` deve lançar `AuditLogIsImmutableException` ao
     * tentar fazer `save()` em um registro existente — ANTES de atingir o BD
     * (defesa no Model boot).
     */
    public function test_model_save_throws_on_update(): void
    {
        $log = AuditLog::create([
            'actor_type' => 'system',
            'action' => 'test.model.update',
            'payload' => [],
        ]);

        $this->expectException(AuditLogIsImmutableException::class);

        $log->action = 'hacked';
        $log->save();
    }

    /**
     * O Model `AuditLog` deve lançar `AuditLogIsImmutableException` ao
     * tentar chamar `delete()` — ANTES de atingir o BD.
     */
    public function test_model_delete_throws(): void
    {
        $log = AuditLog::create([
            'actor_type' => 'system',
            'action' => 'test.model.delete',
            'payload' => [],
        ]);

        $this->expectException(AuditLogIsImmutableException::class);

        $log->delete();
    }
}
