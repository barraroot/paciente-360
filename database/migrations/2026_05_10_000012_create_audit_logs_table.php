<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela `audit_logs` — log de auditoria imutável (FR-035, Princípio I).
 *
 * Schema conforme `specs/001-fundacao-multitenant/data-model.md` § 9.
 *
 * Notas:
 *  - Sem `updated_at`: registros são semanticamente imutáveis. O Model
 *    também lança exceção em `save()` quando `id` está preenchido.
 *  - Trigger PG `audit_logs_immutable_trg` rejeita UPDATE e DELETE diretamente
 *    no banco (defesa em profundidade — protege mesmo contra acesso raw via
 *    psql ou ferramentas externas).
 *  - `tenant_id` NULLABLE: NULL = ação do Super Admin sem tenant alvo (raro).
 *  - `user_id` NULLABLE: NULL = ação de sistema (job, webhook, scheduler).
 *  - `actor_type` via CHECK (não ENUM PG) para extensibilidade.
 *  - `payload` JSONB: snapshot de `old`/`new` ou contexto livre, SEM PII
 *    desnecessária (LGPD — Princípio I).
 *  - BRIN em `created_at`: tabela append-only com crescimento linear;
 *    BRIN é muito mais leve que B-tree para range scans em séries temporais.
 *  - Índice `(tenant_id, created_at DESC)` é a query principal do painel
 *    de auditoria, que lista eventos do tenant mais recentes primeiro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
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

        // CHECK constraint para actor_type.
        DB::statement(<<<'SQL'
            ALTER TABLE audit_logs
            ADD CONSTRAINT audit_logs_actor_type_check
            CHECK (actor_type IN ('user', 'system', 'webhook'))
        SQL);

        // Índice composto principal: listagem paginada por tenant, mais
        // recente primeiro.
        DB::statement(<<<'SQL'
            CREATE INDEX audit_tenant_created_idx
            ON audit_logs (tenant_id, created_at DESC)
        SQL);

        // Índice para filtro por tipo de ação (monitoramento de eventos
        // específicos, ex.: todos os `user.login.failed`).
        DB::statement(<<<'SQL'
            CREATE INDEX audit_action_created_idx
            ON audit_logs (action, created_at DESC)
        SQL);

        // Índice para lookup por entidade auditada (ex.: ver todo histórico
        // de um Tenant específico, de uma Subscription, etc.).
        DB::statement(<<<'SQL'
            CREATE INDEX audit_auditable_idx
            ON audit_logs (auditable_type, auditable_id)
        SQL);

        // BRIN: extremamente eficiente para range scans em tabelas append-only
        // ordenadas por tempo. Usado pelo job de arquivamento mensal.
        DB::statement(<<<'SQL'
            CREATE INDEX audit_created_at_brin
            ON audit_logs USING BRIN (created_at)
        SQL);

        // Função e trigger de imutabilidade: rejeita UPDATE e DELETE no banco,
        // independentemente de quem executa a query (Laravel, psql, scripts).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_logs_immutable()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs is append-only: UPDATE and DELETE are not permitted';
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER audit_logs_immutable_trg
            BEFORE UPDATE OR DELETE ON audit_logs
            FOR EACH ROW EXECUTE FUNCTION audit_logs_immutable()
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS audit_logs_immutable_trg ON audit_logs');
        DB::statement('DROP FUNCTION IF EXISTS audit_logs_immutable()');
        Schema::dropIfExists('audit_logs');
    }
};
