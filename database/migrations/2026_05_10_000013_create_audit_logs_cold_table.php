<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela `audit_logs_cold` — arquivo de auditoria de longo prazo.
 *
 * Schema conforme `specs/001-fundacao-multitenant/data-model.md` § 9.b.
 *
 * Notas:
 *  - Mesmo schema de `audit_logs` mas com índices reduzidos: apenas BRIN
 *    em `created_at` + índice simples em `tenant_id`. Registros cold são
 *    consultados raramente (retenção 5 anos, compliance) e não precisam
 *    dos índices pesados da tabela hot.
 *  - Carregada pelo job `ArchiveAuditLogsJob` (mensal) que move registros
 *    com `created_at < now() - hot_days` (config `audit.hot_days` = 730).
 *  - Trigger de imutabilidade separada (`audit_logs_cold_immutable`) para
 *    independência: drops da tabela cold não afetam a função da tabela hot.
 *  - Sem `updated_at` (mesma semântica imutável da tabela hot).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs_cold', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');

            $table->string('actor_type', 20);
            $table->string('action', 100);

            $table->string('auditable_type', 150)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();

            $table->jsonb('payload')->default('{}');

            $table->ipAddress('ip')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('request_id', 50)->nullable();

            $table->timestampTz('created_at')->useCurrent();
            // Sem updated_at: registros imutáveis.
        });

        DB::statement(<<<'SQL'
            ALTER TABLE audit_logs_cold
            ADD CONSTRAINT audit_logs_cold_actor_type_check
            CHECK (actor_type IN ('user', 'system', 'webhook'))
        SQL);

        // BRIN é o índice primário aqui: cold table cresce sequencialmente
        // e é consultada apenas por range de datas (compliance/auditoria).
        DB::statement(<<<'SQL'
            CREATE INDEX audit_logs_cold_created_at_brin
            ON audit_logs_cold USING BRIN (created_at)
        SQL);

        // Função de imutabilidade separada: nomeada distintamente para que
        // o drop de audit_logs_cold não afete audit_logs e vice-versa.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_logs_cold_immutable()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs_cold is append-only: UPDATE and DELETE are not permitted';
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER audit_logs_cold_immutable_trg
            BEFORE UPDATE OR DELETE ON audit_logs_cold
            FOR EACH ROW EXECUTE FUNCTION audit_logs_cold_immutable()
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS audit_logs_cold_immutable_trg ON audit_logs_cold');
        DB::statement('DROP FUNCTION IF EXISTS audit_logs_cold_immutable()');
        Schema::dropIfExists('audit_logs_cold');
    }
};
