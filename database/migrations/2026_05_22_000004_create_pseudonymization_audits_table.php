<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T070 (Fase 8 — Lote A US-13.3)** — Tabela `pseudonymization_audits` (GLOBAL).
 *
 * Histórico de cada execução de auditoria de pseudonimização (Q29):
 *   - `static_reflection`: rodada pelo CI gate (test EventsForAiPseudonymizationTest)
 *     ou ad-hoc via `PseudonymizationAuditor::runStaticReflection()`.
 *   - `runtime_replay`: rodada semanal pelo cron `privacy:audit-pseudonymization-weekly`
 *     que aplica `PiiDetector` sobre amostra de 1% dos audit_logs persistidos.
 *
 * Tabela é GLOBAL (sem `tenant_id`) — auditoria é exercício de plataforma,
 * não tenant-scoped. Princípio II preservado: nenhum dado de paciente vaza
 * na tabela; findings carregam apenas pattern_name + field_path (não o valor).
 *
 * @see specs/008-finalizacao-mvp/data-model.md §1.4
 * @see specs/008-finalizacao-mvp/research.md §1 Q29
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'pseudonymization_audit_mode_enum') THEN
                    CREATE TYPE pseudonymization_audit_mode_enum AS ENUM (
                        'static_reflection',
                        'runtime_replay'
                    );
                END IF;
            END
            $$;
        SQL);

        Schema::create('pseudonymization_audits', function (Blueprint $table): void {
            $table->id();

            $table->timestampTz('audited_at');

            $table->unsignedBigInteger('audited_by_user_id')->nullable();
            $table->foreign('audited_by_user_id')->references('id')->on('users')->nullOnDelete();
            // NULL = execução automática via cron.

            $table->string('mode', 30);

            $table->json('scope_event_types');
            // Lista de event_types varridos.

            $table->unsignedInteger('sample_size')->nullable();
            // Apenas para runtime_replay — quantidade randômica de eventos amostrados.

            $table->unsignedInteger('total_events_scanned');
            $table->unsignedInteger('non_conformant_events')->default(0);

            $table->json('findings')->nullable();
            // Array de `{event_id, field_path, pattern_matched, severity}`
            // SEM o valor real do PII detectado.

            $table->text('report_summary')->nullable();
            // Markdown opcional para painel.

            $table->timestampsTz();

            $table->index(['audited_at'], 'idx_pseudo_audits_recent');
            $table->index(['mode', 'audited_at'], 'idx_pseudo_audits_by_mode');
        });

        DB::statement('ALTER TABLE pseudonymization_audits ALTER COLUMN mode TYPE pseudonymization_audit_mode_enum USING mode::pseudonymization_audit_mode_enum');
    }

    public function down(): void
    {
        Schema::dropIfExists('pseudonymization_audits');
        DB::statement('DROP TYPE IF EXISTS pseudonymization_audit_mode_enum');
    }
};
