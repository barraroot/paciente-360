<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona `executor_id` em `audit_logs_cold` para emparelhar com `audit_logs`.
 *
 * Bug latente desde Fase 3 (2026_05_12_031846_add_executor_id_to_audit_logs):
 * a coluna foi adicionada apenas à tabela hot, mas o job
 * `ArchiveAuditLogsJob` copia TODAS as colunas via `(array) $row` → INSERT em
 * cold falha com "column executor_id does not exist". Os testes
 * `AuditRetentionTest::test_archive_*` capturavam o erro mas não tinham
 * sido contemplados em uma correção subsequente.
 *
 * Fix descoberto durante o run full do Lote G (Fase 4 — token auth migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs_cold', function (Blueprint $table): void {
            $table->unsignedBigInteger('executor_id')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs_cold', function (Blueprint $table): void {
            $table->dropColumn('executor_id');
        });
    }
};
